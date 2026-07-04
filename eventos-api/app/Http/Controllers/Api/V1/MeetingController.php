<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\NormalizesTimestamps;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Participation;
use App\Services\Notifications\NotificationService;
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
            'invitees' => ['required', 'array', 'min:1'],
            'invitees.*' => ['string'], // participation uuids
        ]);

        $data = $this->utcDates($data, ['starts_at', 'ends_at']);

        $invitees = Participation::where('event_id', $eventId)
            ->whereIn('uuid', $data['invitees'])
            ->where('id', '!=', $me)
            ->get();

        abort_if($invitees->isEmpty(), 422, 'Select at least one person to meet.');

        $meeting = Meeting::create([
            'event_id' => $eventId,
            'organization_id' => $orgId,
            'organizer_participation_id' => $me,
            'title' => $data['title'] ?? null,
            'agenda' => $data['agenda'] ?? null,
            'type' => $data['type'] ?? 'one_on_one',
            'max_participants' => $data['max_participants'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
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
            'counterpart' => $counterpart ? $this->person($counterpart) : null,
            'participants' => $m->participants->map(fn ($p) => [
                'name' => $this->name($p),
                'role' => $p->pivot->role,
                'rsvp' => $p->pivot->rsvp,
            ])->values(),
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
}
