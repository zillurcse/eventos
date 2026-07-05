<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\NormalizesTimestamps;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSetting;
use App\Models\Meeting;
use App\Models\Participation;
use App\Services\Notifications\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    use NormalizesTimestamps;

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

        return response()->json(['data' => $meetings]);
    }

    /**
     * Bookable lounge slots for this event + which of them are already taken by
     * me and (optionally, via ?with=<uuid>) the person I'm about to invite, so
     * the booking picker can offer only the free ones.
     * GET /events/{event}/lounge
     */
    public function lounge(Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $me = $request->attributes->get('participation_id');

        $event = Event::findOrFail($eventId);
        $lounge = $this->loungeConfig($eventId);

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
            'enabled' => (bool) ($lounge['enabled'] ?? false),
            'slots_open_all' => (bool) ($lounge['slots_open_all'] ?? false),
            'timezone' => $event->resolvedTimezone(),
            'dates' => $this->loungeDates($event),
            'slots' => $this->effectiveSlots($event, $lounge),
            'busy' => $busy,
        ]]);
    }

    public function store(Request $request, NotificationService $notifications): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $orgId = $request->attributes->get('organization_id');
        $me = $request->attributes->get('participation_id');

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'agenda' => ['nullable', 'string', 'max:1000'],
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

        // Resolve a lounge-slot booking into concrete start/end + a canonical
        // slot key, enforcing that the slot is offered and not already taken.
        $startsAt = $data['starts_at'] ?? null;
        $endsAt = $data['ends_at'] ?? null;
        $meta = null;

        if (! empty($data['slot']) && ! empty($data['date'])) {
            $event = Event::findOrFail($eventId);
            $effective = $this->effectiveSlots($event, $this->loungeConfig($eventId));

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
            'type' => $data['type'] ?? 'one_on_one',
            'max_participants' => $data['max_participants'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'meta' => $meta,
            'status' => 'requested',
        ]);

        $meeting->participants()->attach($me, ['role' => 'host', 'rsvp' => 'accepted']);

        $organizerName = $this->name($meeting->organizer);

        foreach ($invitees as $invitee) {
            $meeting->participants()->attach($invitee->id, ['role' => 'guest', 'rsvp' => 'pending']);

            $notifications->notify(
                'participation', $invitee->id, $orgId, $eventId,
                'meeting.requested',
                [
                    'title' => 'New meeting request',
                    'body' => $organizerName.' wants to meet'.($meeting->title ? ' — '.$meeting->title : '').'.',
                    'meeting_id' => $meeting->uuid,
                ],
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

            foreach ($record->participants->where('pivot.role', 'guest') as $guest) {
                $notifications->notify(
                    'participation', $guest->id, $orgId, $eventId,
                    'meeting.canceled',
                    ['title' => 'Meeting canceled', 'body' => $this->name($record->organizer).' canceled the meeting.', 'meeting_id' => $record->uuid],
                );
            }

            return response()->json(['data' => $this->format($record->fresh('participants.contact'), $me)]);
        }

        // Accept / reject — must be an invited guest with a pending RSVP.
        $mine = $record->participants->firstWhere('id', $me);
        abort_if(! $mine || $mine->pivot->role !== 'guest', 403, 'You were not invited to this meeting.');
        abort_if($mine->pivot->rsvp !== 'pending', 422, 'You have already responded to this request.');

        $rsvp = $data['action'] === 'accept' ? 'accepted' : 'declined';
        $record->participants()->updateExistingPivot($me, ['rsvp' => $rsvp]);

        // For one-on-one the RSVP is the whole decision; groups stay "requested"
        // until the organizer wraps up (a single accept confirms it).
        $record->update(['status' => $data['action'] === 'accept' ? 'confirmed' : 'declined']);

        $notifications->notify(
            'participation', $record->organizer_participation_id, $orgId, $eventId,
            $data['action'] === 'accept' ? 'meeting.confirmed' : 'meeting.declined',
            [
                'title' => $data['action'] === 'accept' ? 'Meeting confirmed' : 'Meeting declined',
                'body' => $this->name($mine).($data['action'] === 'accept' ? ' accepted your meeting request.' : ' declined your meeting request.'),
                'meeting_id' => $record->uuid,
            ],
        );

        return response()->json(['data' => $this->format($record->fresh('participants.contact'), $me)]);
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
            'type' => $m->type,
            'status' => $m->status,
            'direction' => $direction,
            'my_rsvp' => $myRsvp,
            'can_respond' => $direction === 'incoming' && $myRsvp === 'pending' && $m->status === 'requested',
            'starts_at' => $m->starts_at?->toIso8601String(),
            'ends_at' => $m->ends_at?->toIso8601String(),
            'date' => $m->meta['lounge_date'] ?? null,
            'slot' => $m->meta['lounge_slot'] ?? null,
            'counterpart' => $counterpart ? $this->person($counterpart) : null,
            'participants' => $m->participants->map(fn ($p) => [
                'name' => $this->name($p),
                'role' => $p->pivot->role,
                'rsvp' => $p->pivot->rsvp,
            ])->values(),
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
     * full half-hour grid when "Open all meeting slot" is on.
     */
    private function effectiveSlots(Event $event, array $lounge): array
    {
        $dates = $this->loungeDates($event);

        if (! empty($lounge['slots_open_all'])) {
            $grid = $this->fullDayGrid();

            return collect($dates)->mapWithKeys(fn ($d) => [$d => $grid])->all();
        }

        $configured = is_array($lounge['slots'] ?? null) ? $lounge['slots'] : [];
        $out = [];
        foreach ($dates as $d) {
            $out[$d] = array_values(array_filter((array) ($configured[$d] ?? []), 'is_string'));
        }

        return $out;
    }

    /** Half-hour grid 10:00–18:00 — mirrors the admin slot manager. */
    private function fullDayGrid(): array
    {
        $slots = [];
        for ($h = 10; $h < 18; $h++) {
            $slots[] = sprintf('%02d:00-%02d:30', $h, $h);
            $slots[] = sprintf('%02d:30-%02d:00', $h, $h + 1);
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
}
