<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesMeetingLocation;
use App\Http\Controllers\Concerns\NormalizesTimestamps;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSetting;
use App\Models\ExhibitorMeetingRequest;
use App\Models\ExhibitorMember;
use App\Models\Meeting;
use App\Models\Participation;
use App\Support\CommunicationCapabilities;
use App\Support\IntelligentMeeting;
use App\Support\MeetingCapabilities;
use App\Services\BreakoutRoom\Providers\LiveKitProvider;
use App\Services\Notifications\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Meetings — one-to-one OR group (architecture §6.5). The organizer is the
 * resolved participation; invitees join via the meeting_participants pivot.
 *
 * Flow for the "Meetings" tab: a participant sends a request to another
 * participant (store) → the invitee sees it as an incoming pending request and
 * approves or rejects it (respond) → the organizer is notified of the outcome.
 * Acts as the resolved participation (ResolveParticipant middleware).
 */
class MeetingController extends Controller
{
    use HandlesMeetingLocation, NormalizesTimestamps;

    // How early you may join before starts_at, how long an untimed slot is
    // assumed to run, and how long after the end the room still admits you.
    private const JOIN_LEAD_MINUTES = 10;

    private const DEFAULT_DURATION_MINUTES = 30;

    private const JOIN_GRACE_MINUTES = 15;

    public function __construct(private LiveKitProvider $livekit) {}

    /** GET /events/{event}/meetings/capabilities — role matrix + caps for the caller. */
    public function capabilities(Request $request): JsonResponse
    {
        $eventId = (int) $request->attributes->get('event_id');
        $me = Participation::findOrFail($request->attributes->get('participation_id'));
        $meeting = MeetingCapabilities::configForEvent($eventId);

        return response()->json([
            'data' => MeetingCapabilities::forParticipant($me, $meeting, $eventId),
        ]);
    }

    /**
     * Meeting area map — attendee tables and their slot bookings.
     * Only meaningful when Intelligent Meeting is enabled.
     * GET /events/{event}/meetings/area
     */
    public function area(Request $request): JsonResponse
    {
        $eventId = (int) $request->attributes->get('event_id');
        $meeting = MeetingCapabilities::configForEvent($eventId);

        abort_unless(
            MeetingCapabilities::isIntelligent($meeting),
            404,
            'Meeting area map is not enabled for this event.',
        );

        return response()->json(['data' => [
            'tables' => IntelligentMeeting::areaMap($eventId),
        ]]);
    }

    /**
     * People the caller may request a meeting with — honors the meeting permission
     * matrix, block flags and networking opt-out (mirrors chat/partners).
     */
    public function partners(Request $request): JsonResponse
    {
        $eventId = (int) $request->attributes->get('event_id');
        $me = (int) $request->attributes->get('participation_id');
        $meP = Participation::findOrFail($me);
        $meeting = MeetingCapabilities::configForEvent($eventId);

        if (! MeetingCapabilities::meetingsTabEnabled(EventSetting::where('event_id', $eventId)->first())) {
            return response()->json(['data' => [], 'roles' => []]);
        }

        $myRole = CommunicationCapabilities::roleFor($meP);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:attendee,speaker,exhibitor,sponsor'],
        ]);

        $allowedRoles = MeetingCapabilities::allowedTargetRoles($meeting, $myRole);
        if (! empty($data['role'])) {
            $allowedRoles = array_values(array_intersect($allowedRoles, [$data['role']]));
        }
        if (! $allowedRoles) {
            return response()->json(['data' => [], 'roles' => []]);
        }

        $query = Participation::query()
            ->with('contact')
            ->select('participations.*')
            ->join('contacts', 'contacts.id', '=', 'participations.contact_id')
            ->where('participations.event_id', $eventId)
            ->whereIn('participations.role', ['attendee', 'speaker', 'sponsor', 'partner_member', 'exhibitor'])
            ->where('participations.id', '!=', $me)
            ->where(fn ($q) => $q->whereNull('participations.meta->blocked')->orWhere('participations.meta->blocked', false))
            ->where(fn ($q) => $q->whereNull('participations.networking_opt_in')->orWhere('participations.networking_opt_in', true));

        if (! empty($data['q'])) {
            $term = '%'.$data['q'].'%';
            $query->where(fn ($q) => $q
                ->whereRaw("coalesce(contacts.first_name,'')||' '||coalesce(contacts.last_name,'') ilike ?", [$term])
                ->orWhere('contacts.company', 'ilike', $term));
        }

        $people = $query
            ->orderByRaw("lower(coalesce(contacts.first_name,'')||' '||coalesce(contacts.last_name,''))")
            ->limit(100)
            ->get()
            ->filter(fn (Participation $p) => MeetingCapabilities::allowsFor($meP, $p, $meeting))
            ->map(fn (Participation $p) => $this->partnerPerson($p))
            ->values();

        return response()->json([
            'data' => $people,
            'roles' => MeetingCapabilities::allowedTargetRoles($meeting, $myRole),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $me = $request->attributes->get('participation_id');

        $meetings = Meeting::with('participants.contact')
            ->where(fn ($q) => $q
                ->where('organizer_participation_id', $me)
                ->orWhereHas('participants', fn ($p) => $p->where('participations.id', $me)))
            ->latest('id')
            ->get()
            ->map(fn (Meeting $m) => $this->format($m, $me))
            ->values();

        // Booth meetings (attendee ↔ exhibitor) live in their own table, but to
        // the person in either seat they are simply "my meetings" — so they
        // belong on this tab alongside the delegate ones. Same status vocabulary,
        // so they bucket into Pending/Approved/Rejected unchanged.
        $meetings = $meetings
            ->concat($this->exhibitorMeetings($request, (int) $me))
            ->sortByDesc('created_at')
            ->values();

        return response()->json(['data' => $meetings]);
    }

    /**
     * The booth meetings this viewer is in — either because they requested one
     * (as an attendee) or because their booth assigned one to them (as a member
     * of the exhibitor's team).
     *
     * @return \Illuminate\Support\Collection<int,array<string,mixed>>
     */
    private function exhibitorMeetings(Request $request, int $me): Collection
    {
        $eventId = (int) $request->attributes->get('event_id');

        $participation = Participation::find($me);

        // The exhibitor_members rows for this person, if they staff any booth.
        $myMemberIds = $participation?->contact_id
            ? ExhibitorMember::where('contact_id', $participation->contact_id)->pluck('id')
            : collect();

        $requests = ExhibitorMeetingRequest::with(['exhibitor', 'participation.contact', 'assignedMember.contact'])
            ->where('event_id', $eventId)
            ->where(fn ($q) => $q
                ->where('participation_id', $me)                       // I asked for it
                ->orWhereIn('assigned_member_id', $myMemberIds))       // it was assigned to me
            ->latest('id')
            ->get();

        return $requests->map(fn (ExhibitorMeetingRequest $r) => $this->formatExhibitorMeeting($r, $me));
    }

    /**
     * Shape a booth meeting like a delegate one, so the Meetings tab can render
     * both from a single list. `source` lets the UI badge it as a booth meeting.
     */
    private function formatExhibitorMeeting(ExhibitorMeetingRequest $r, int $me): array
    {
        // From the attendee's seat the request points out to the booth; from the
        // assigned member's seat it points in at them.
        $direction = (int) $r->participation_id === $me ? 'outgoing' : 'incoming';

        $counterpart = $direction === 'outgoing'
            ? ['name' => $r->exhibitor->name ?? 'Exhibitor', 'company' => '', 'job_title' => 'Exhibitor', 'avatar_url' => null]
            : $this->person($r->participation);

        return [
            'id' => $r->uuid,
            'title' => $r->subject,
            'agenda' => $r->agenda,
            'location' => $r->location,
            'type' => 'one_on_one',
            'status' => $r->status,
            'direction' => $direction,
            'my_rsvp' => $r->status === 'confirmed' ? 'accepted' : 'pending',
            // Booth meetings are answered by the exhibitor team in their own
            // panel (Inbox → Meeting Requests), never from this tab.
            'can_respond' => false,
            'starts_at' => $r->starts_at?->toIso8601String(),
            'ends_at' => $r->ends_at?->toIso8601String(),
            'date' => $r->meta['lounge_date'] ?? null,
            'slot' => $r->meta['lounge_slot'] ?? null,
            'counterpart' => $counterpart,
            'participants' => [],
            'source' => 'exhibitor',
            'exhibitor' => $r->exhibitor->name ?? null,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }

    /**
     * Everything the booking picker needs: the bookable lounge slots for this
     * event, which of them are already taken by me and (optionally, via
     * ?with=<uuid>) the person I'm about to invite, and — on a venue/hybrid
     * event — where the meeting may take place.
     * GET /events/{event}/lounge
     */
    public function lounge(Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $me = $request->attributes->get('participation_id');

        $event = Event::findOrFail($eventId);
        $lounge = $this->loungeConfig($eventId);
        $meetingConfig = MeetingCapabilities::configForEvent((int) $eventId);
        $intelligent = MeetingCapabilities::isIntelligent($meetingConfig);

        $partyIds = [$me];
        if ($with = $request->query('with')) {
            $counterpart = Participation::where('event_id', $eventId)->where('uuid', $with)->first();
            if ($counterpart) {
                $partyIds[] = $counterpart->id;
            }
        }

        $busy = Meeting::where('event_id', $eventId)
            ->whereIn('status', ['requested', 'confirmed'])
            ->whereNotNull('meta')
            ->where(fn ($q) => $q
                ->whereIn('organizer_participation_id', $partyIds)
                ->orWhereHas('participants', fn ($p) => $p->whereIn('participations.id', $partyIds)))
            ->get(['id', 'meta'])
            ->map(fn (Meeting $m) => ['date' => $m->meta['lounge_date'] ?? null, 'slot' => $m->meta['lounge_slot'] ?? null])
            ->filter(fn ($x) => $x['date'] && $x['slot'])
            ->unique(fn ($x) => $x['date'].'|'.$x['slot'])
            ->values();

        return response()->json(['data' => [
            'enabled' => $intelligent || (bool) ($lounge['enabled'] ?? false),
            'intelligent' => $intelligent,
            'slots_open_all' => $intelligent || (bool) ($lounge['slots_open_all'] ?? false),
            'timezone' => $event->resolvedTimezone(),
            'dates' => $this->loungeDates($event),
            'slots' => $this->effectiveSlots($event, $lounge, $meetingConfig),
            'busy' => $busy,
            // Where the meeting happens. On an online event there is nowhere to
            // be, so the picker hides the field entirely.
            'format' => $event->format,
            'location_required' => ! $intelligent && $this->isPhysicalEvent($event),
            'locations' => $this->meetingLocationOptions((int) $eventId),
        ]]);
    }

    public function store(Request $request, NotificationService $notifications): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $orgId = $request->attributes->get('organization_id');
        $me = $request->attributes->get('participation_id');

        $event = Event::findOrFail($eventId);
        $meP = Participation::findOrFail($me);
        $meetingConfig = MeetingCapabilities::configForEvent((int) $eventId);

        MeetingCapabilities::abortUnlessMeetingsEnabled((int) $eventId);
        MeetingCapabilities::abortUnlessCanSendRequest((int) $eventId, $meP, $meetingConfig);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'agenda' => ['nullable', 'string', 'max:1000'],
            // Required on a venue/hybrid event: the two of them have to meet
            // somewhere ("Hall 4"). Ignored on an online event.
            'location' => $this->meetingLocationRules($event, $meetingConfig),
            'type' => ['nullable', 'in:one_on_one,group'],
            'max_participants' => ['nullable', 'integer', 'min:2'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            // A booking into one of the organizer's lounge slots (preferred).
            'date' => ['nullable', 'date_format:Y-m-d', 'required_with:slot'],
            'slot' => ['nullable', 'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/', 'required_with:date'],
            'invitees' => ['required', 'array', 'min:1'],
            'invitees.*' => ['string'], // participation uuids
        ]);

        $data = $this->utcDates($data, ['starts_at', 'ends_at']);

        $invitees = Participation::where('event_id', $eventId)
            ->whereIn('uuid', $data['invitees'])
            ->where('id', '!=', $me)
            ->get();

        abort_if($invitees->isEmpty(), 422, 'Select at least one person to meet.');

        foreach ($invitees as $invitee) {
            abort_unless(
                MeetingCapabilities::allowsFor($meP, $invitee, $meetingConfig),
                403,
                'The organizer has not enabled meetings with this role.',
            );
        }

        if (
            MeetingCapabilities::isIntelligent($meetingConfig)
            && $this->isPhysicalEvent($event)
            && (empty($data['date']) || empty($data['slot']))
        ) {
            abort(422, 'Pick a time slot for your meeting.');
        }

        // Resolve a lounge-slot booking into concrete start/end + a canonical
        // slot key, enforcing that the slot is offered and not already taken.
        $startsAt = $data['starts_at'] ?? null;
        $endsAt = $data['ends_at'] ?? null;
        $meta = null;

        if (! empty($data['slot']) && ! empty($data['date'])) {
            $effective = $this->effectiveSlots($event, $this->loungeConfig($eventId), $meetingConfig);

            abort_unless(
                in_array($data['slot'], $effective[$data['date']] ?? [], true),
                422, 'That time slot is not available for the selected day.',
            );

            $partyIds = $invitees->pluck('id')->push($me)->all();
            abort_if(
                $this->slotIsTaken($eventId, $data['date'], $data['slot'], $partyIds),
                422, 'That slot is already booked. Please pick another one.',
            );

            [$startHM, $endHM] = explode('-', $data['slot']);
            $tz = $event->resolvedTimezone();
            $startsAt = Carbon::createFromFormat('Y-m-d H:i', $data['date'].' '.$startHM, $tz)->utc();
            $endsAt = Carbon::createFromFormat('Y-m-d H:i', $data['date'].' '.$endHM, $tz)->utc();
            $meta = ['lounge_date' => $data['date'], 'lounge_slot' => $data['slot']];
        }

        $meeting = Meeting::create([
            'event_id' => $eventId,
            'organization_id' => $orgId,
            'organizer_participation_id' => $me,
            'title' => $data['title'] ?? null,
            'agenda' => $data['agenda'] ?? null,
            'location' => $this->meetingLocationValue($event, $data['location'] ?? null),
            'type' => $data['type'] ?? 'one_on_one',
            'max_participants' => $data['max_participants'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'meta' => $meta,
            'status' => 'requested',
        ]);

        $meeting->participants()->attach($me, ['role' => 'host', 'rsvp' => 'accepted']);

        $organizerName = $this->name($meeting->organizer);

        $channels = $notifications->channelsForEventAction((int) $eventId, 'meeting');

        foreach ($invitees as $invitee) {
            $meeting->participants()->attach($invitee->id, ['role' => 'guest', 'rsvp' => 'pending']);

            if ($channels === []) {
                continue;
            }

            $notifications->notify(
                'participation', $invitee->id, $orgId, $eventId,
                'meeting.requested',
                [
                    'title' => 'New meeting request',
                    'body' => $organizerName.' wants to meet'.($meeting->title ? ' — '.$meeting->title : '').'.',
                    'meeting_id' => $meeting->uuid,
                ],
                $channels,
            );
        }

        return response()->json(['data' => $this->format($meeting->fresh('participants.contact'), $me)], 201);
    }

    /**
     * Approve / reject an incoming request, or cancel one you organized.
     * PATCH /events/{event}/meetings/{meeting}
     */
    public function respond(string $event, string $meeting, Request $request, NotificationService $notifications): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $orgId = $request->attributes->get('organization_id');
        $me = $request->attributes->get('participation_id');

        $data = $request->validate(['action' => ['required', 'in:accept,reject,cancel']]);

        $record = Meeting::with('participants.contact')
            ->where('uuid', $meeting)
            ->where('event_id', $eventId)
            ->firstOrFail();

        // Cancel — organizer only. Withdraws a request or a confirmed meeting.
        if ($data['action'] === 'cancel') {
            abort_unless($record->organizer_participation_id == $me, 403, 'Only the organizer can cancel this meeting.');

            $record->update(['status' => 'canceled']);

            $channels = $notifications->channelsForEventAction((int) $eventId, 'meeting');
            foreach ($record->participants->where('pivot.role', 'guest') as $guest) {
                if ($channels === []) {
                    break;
                }
                $notifications->notify(
                    'participation', $guest->id, $orgId, $eventId,
                    'meeting.canceled',
                    ['title' => 'Meeting canceled', 'body' => $this->name($record->organizer).' canceled the meeting.', 'meeting_id' => $record->uuid],
                    $channels,
                );
            }

            return response()->json(['data' => $this->format($record->fresh('participants.contact'), $me)]);
        }

        // Accept / reject — must be an invited guest with a pending RSVP.
        $mine = $record->participants->firstWhere('id', $me);
        abort_if(! $mine || $mine->pivot->role !== 'guest', 403, 'You were not invited to this meeting.');
        abort_if($mine->pivot->rsvp !== 'pending', 422, 'You have already responded to this request.');

        if ($data['action'] === 'accept') {
            $meetingConfig = MeetingCapabilities::configForEvent((int) $eventId);
            MeetingCapabilities::abortUnlessCanConfirm((int) $eventId, $mine, $meetingConfig);

            $organizer = Participation::find($record->organizer_participation_id);
            if ($organizer) {
                MeetingCapabilities::abortUnlessCanConfirm(
                    (int) $eventId,
                    $organizer,
                    $meetingConfig,
                    'This meeting cannot be confirmed because the requester has reached their confirmed meeting limit.',
                );
            }

            $event = Event::findOrFail($eventId);
            if (
                MeetingCapabilities::isIntelligent($meetingConfig)
                && $this->isPhysicalEvent($event)
            ) {
                $date = $record->meta['lounge_date'] ?? null;
                $slot = $record->meta['lounge_slot'] ?? null;
                if ($date && $slot) {
                    $table = IntelligentMeeting::allocateTable((int) $eventId, $date, $slot, $record->id);
                    if ($table) {
                        $meta = is_array($record->meta) ? $record->meta : [];
                        $meta['allocated_table_id'] = $table['id'];
                        $meta['allocated_table_name'] = $table['name'];
                        $area = MeetingCapabilities::locations($meetingConfig)[0] ?? null;
                        $record->location = $area ? $area.' · '.$table['name'] : $table['name'];
                        $record->meta = $meta;
                    }
                }
            }
        }

        $rsvp = $data['action'] === 'accept' ? 'accepted' : 'declined';
        $record->participants()->updateExistingPivot($me, ['rsvp' => $rsvp]);

        // For one-on-one the RSVP is the whole decision; groups stay "requested"
        // until the organizer wraps up (a single accept confirms it).
        $updates = ['status' => $data['action'] === 'accept' ? 'confirmed' : 'declined'];
        if ($data['action'] === 'accept' && $record->isDirty(['location', 'meta'])) {
            $updates['location'] = $record->location;
            $updates['meta'] = $record->meta;
        }
        $record->update($updates);

        $channels = $notifications->channelsForEventAction((int) $eventId, 'meeting');
        if ($channels !== []) {
            $notifications->notify(
                'participation', $record->organizer_participation_id, $orgId, $eventId,
                $data['action'] === 'accept' ? 'meeting.confirmed' : 'meeting.declined',
                [
                    'title' => $data['action'] === 'accept' ? 'Meeting confirmed' : 'Meeting declined',
                    'body' => $this->name($mine).($data['action'] === 'accept' ? ' accepted your meeting request.' : ' declined your meeting request.'),
                    'meeting_id' => $record->uuid,
                ],
                $channels,
            );
        }

        return response()->json(['data' => $this->format($record->fresh('participants.contact'), $me)]);
    }

    /**
     * Mint a LiveKit join config for a confirmed one-to-one meeting that is
     * currently running. Untimed meetings (no proposed time was ever set) have
     * no window to enforce — they're joinable any time once confirmed.
     * POST /events/{event}/meetings/{meeting}/join
     */
    public function join(string $event, string $meeting, Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $me = $request->attributes->get('participation_id');

        $record = Meeting::with('participants.contact')
            ->where('uuid', $meeting)
            ->where('event_id', $eventId)
            ->firstOrFail();

        abort_unless($record->type === 'one_on_one', 422, 'Group meetings don\'t join over video here.');
        abort_unless($record->status === 'confirmed', 422, 'This meeting hasn\'t been confirmed yet.');

        $mine = $record->participants->firstWhere('id', $me);
        abort_unless($mine, 403, 'You are not part of this meeting.');

        if ($record->starts_at) {
            $opensAt = $record->starts_at->copy()->subMinutes(self::JOIN_LEAD_MINUTES);
            $endsAt = $record->ends_at ?? $record->starts_at->copy()->addMinutes(self::DEFAULT_DURATION_MINUTES);
            $closesAt = $endsAt->copy()->addMinutes(self::JOIN_GRACE_MINUTES);

            abort_if(now()->lt($opensAt), 422, 'This meeting hasn\'t started yet.');
            abort_if(now()->gt($closesAt), 422, 'This meeting has already ended.');
        }

        $config = $this->livekit->joinConfigForRoom('meeting_'.$record->uuid, [
            'identity' => 'user_'.($request->user()?->uuid ?? 'guest'),
            'name' => $this->name($mine),
            'role' => 'attendee',
            'canPublish' => true,
        ]);

        return response()->json(['data' => $config + ['title' => $record->title ?: 'Meeting']]);
    }

    /**
     * Shape a meeting for the current viewer: which way the request points, my
     * own RSVP, and the counterpart (the other person on a one-on-one).
     */
    private function format(Meeting $m, int $me): array
    {
        $direction = $m->organizer_participation_id == $me ? 'outgoing' : 'incoming';
        $mine = $m->participants->firstWhere('id', $me);
        $myRsvp = $mine?->pivot->rsvp ?? ($direction === 'outgoing' ? 'accepted' : 'pending');

        // Counterpart: on an outgoing request it's the first guest; on an
        // incoming one it's the organizer (host).
        $counterpart = $direction === 'outgoing'
            ? $m->participants->firstWhere('pivot.role', 'guest')
            : $m->participants->firstWhere('id', $m->organizer_participation_id);

        return [
            'id' => $m->uuid,
            'title' => $m->title,
            'agenda' => $m->agenda,
            'location' => $m->location,
            'type' => $m->type,
            'status' => $m->status,
            'direction' => $direction,
            'my_rsvp' => $myRsvp,
            'can_respond' => $direction === 'incoming' && $myRsvp === 'pending' && $m->status === 'requested',
            'starts_at' => $m->starts_at?->toIso8601String(),
            'ends_at' => $m->ends_at?->toIso8601String(),
            'date' => $m->meta['lounge_date'] ?? null,
            'slot' => $m->meta['lounge_slot'] ?? null,
            'allocated_table' => $this->allocatedTable($m),
            'counterpart' => $counterpart ? $this->person($counterpart) : null,
            'participants' => $m->participants->map(fn ($p) => [
                'name' => $this->name($p),
                'role' => $p->pivot->role,
                'rsvp' => $p->pivot->rsvp,
            ])->values(),
            'source' => 'delegate',
            'exhibitor' => null,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    // ── Lounge slot helpers ────────────────────────────────────────────────

    /** The `lounge` jsonb config for the event (Communication → Lounge). */
    private function loungeConfig(int $eventId): array
    {
        $s = EventSetting::where('event_id', $eventId)->first();

        return is_array($s?->lounge) ? $s->lounge : [];
    }

    /** Event-local dates between starts_at and ends_at (inclusive). */
    private function loungeDates(Event $event): array
    {
        if (! $event->starts_at) {
            return [];
        }

        $tz = $event->resolvedTimezone();
        $day = $event->starts_at->copy()->setTimezone($tz)->startOfDay();
        $last = ($event->ends_at ?? $event->starts_at)->copy()->setTimezone($tz)->startOfDay();

        $dates = [];
        while ($day->lte($last) && count($dates) < 60) {
            $dates[] = $day->format('Y-m-d');
            $day->addDay();
        }

        return $dates;
    }

    /**
     * The bookable slots per date: the organizer's configured slots, or the
     * full grid when "Open all meeting slot" or Intelligent Meeting is on.
     */
    private function effectiveSlots(Event $event, array $lounge, array $meeting = []): array
    {
        $dates = $this->loungeDates($event);
        $intelligent = MeetingCapabilities::isIntelligent($meeting);

        if ($intelligent || ! empty($lounge['slots_open_all'])) {
            $duration = MeetingCapabilities::slotDurationMinutes($meeting);

            return collect($dates)->mapWithKeys(fn ($d) => [$d => $this->fullDayGrid($duration)])->all();
        }

        $configured = is_array($lounge['slots'] ?? null) ? $lounge['slots'] : [];
        $out = [];
        foreach ($dates as $d) {
            $out[$d] = array_values(array_filter((array) ($configured[$d] ?? []), 'is_string'));
        }

        return $out;
    }

    /** Bookable grid 10:00–18:00 using the organizer's slot duration (10/15/30 min). */
    private function fullDayGrid(int $durationMinutes = 30): array
    {
        $durationMinutes = in_array($durationMinutes, [10, 15, 30], true) ? $durationMinutes : 30;
        $slots = [];
        $startMinutes = 10 * 60;
        $endMinutes = 18 * 60;

        for ($m = $startMinutes; $m + $durationMinutes <= $endMinutes; $m += $durationMinutes) {
            $fromH = intdiv($m, 60);
            $fromM = $m % 60;
            $end = $m + $durationMinutes;
            $toH = intdiv($end, 60);
            $toM = $end % 60;
            $slots[] = sprintf('%02d:%02d-%02d:%02d', $fromH, $fromM, $toH, $toM);
        }

        return $slots;
    }

    /** True if any of the given participants already hold this exact slot. */
    private function slotIsTaken(int $eventId, string $date, string $slot, array $partyIds): bool
    {
        return Meeting::where('event_id', $eventId)
            ->whereIn('status', ['requested', 'confirmed'])
            ->where('meta->lounge_date', $date)
            ->where('meta->lounge_slot', $slot)
            ->where(fn ($q) => $q
                ->whereIn('organizer_participation_id', $partyIds)
                ->orWhereHas('participants', fn ($p) => $p->whereIn('participations.id', $partyIds)))
            ->exists();
    }

    /** Directory row for the meeting partner picker (includes matrix role). */
    private function partnerPerson(Participation $p): array
    {
        $c = $p->contact;
        $meta = $p->meta ?? [];
        $profile = $p->profile_data ?? [];

        return [
            'id' => $p->uuid,
            'name' => $this->name($p),
            'role' => CommunicationCapabilities::roleFor($p),
            'company' => $c?->company ?? ($profile['company'] ?? ''),
            'job_title' => $c?->job_title ?? ($profile['designation'] ?? ''),
            'avatar_url' => $meta['avatar_url'] ?? ($profile['avatar_url'] ?? ($profile['image_url'] ?? null)),
        ];
    }

    /** Public projection of a participation (name, title, avatar). */
    private function person(?Participation $p): ?array
    {
        if (! $p) {
            return null;
        }

        $c = $p->contact;
        $profile = $p->profile_data ?? [];
        $meta = $p->meta ?? [];

        return [
            'name' => $this->name($p),
            'company' => $c?->company ?? ($profile['company'] ?? ''),
            'job_title' => $c?->job_title ?? ($profile['designation'] ?? ''),
            'avatar_url' => $meta['avatar_url'] ?? ($profile['avatar_url'] ?? ($profile['image_url'] ?? null)),
        ];
    }

    private function name(?Participation $p): string
    {
        $c = $p?->contact;

        return trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: 'Attendee';
    }

    /** Table assigned by Intelligent Meeting when the invite was accepted. */
    private function allocatedTable(Meeting $m): ?array
    {
        $tableId = $m->meta['allocated_table_id'] ?? null;
        if (! $tableId) {
            return null;
        }

        foreach (IntelligentMeeting::attendeeTables((int) $m->event_id) as $t) {
            if ($t['id'] === $tableId) {
                return $t;
            }
        }

        return [
            'id' => $tableId,
            'name' => $m->meta['allocated_table_name'] ?? 'Table',
            'capacity' => 4,
            'design' => 'round',
            'image_url' => null,
            'accent' => null,
        ];
    }
}
