<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\NormalizesTimestamps;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Meetings — one-to-one OR group (architecture §6.5). The host is the resolved
 * participation; invitees join via the meeting_participants pivot.
 */
class MeetingController extends Controller
{
    use NormalizesTimestamps;

    public function index(Request $request): JsonResponse
    {
        $me = $request->attributes->get('participation_id');

        $meetings = Meeting::with('participants.contact')
            ->where('organizer_participation_id', $me)
            ->orWhereHas('participants', fn ($q) => $q->where('participations.id', $me))
            ->latest('id')
            ->get()
            ->map(fn (Meeting $m) => [
                'id' => $m->uuid,
                'title' => $m->title,
                'type' => $m->type,
                'status' => $m->status,
                'starts_at' => $m->starts_at?->toIso8601String(),
                'participants' => $m->participants->map(fn ($p) => [
                    'name' => trim(($p->contact->first_name ?? '').' '.($p->contact->last_name ?? '')) ?: 'Attendee',
                    'role' => $p->pivot->role,
                    'rsvp' => $p->pivot->rsvp,
                ]),
            ]);

        return response()->json(['data' => $meetings]);
    }

    public function store(Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $me = $request->attributes->get('participation_id');

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'agenda' => ['nullable', 'string', 'max:1000'],
            'type' => ['nullable', 'in:one_on_one,group'],
            'max_participants' => ['nullable', 'integer', 'min:2'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'invitees' => ['array'],
            'invitees.*' => ['string'], // participation uuids
        ]);

        $data = $this->utcDates($data, ['starts_at', 'ends_at']);

        $meeting = Meeting::create([
            'event_id' => $eventId,
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

        $inviteeIds = Participation::where('event_id', $eventId)
            ->whereIn('uuid', $data['invitees'] ?? [])
            ->pluck('id')
            ->reject(fn ($id) => $id == $me);

        foreach ($inviteeIds as $id) {
            $meeting->participants()->attach($id, ['role' => 'guest', 'rsvp' => 'pending']);
        }

        return response()->json([
            'data' => [
                'id' => $meeting->uuid,
                'type' => $meeting->type,
                'status' => $meeting->status,
                'participant_count' => $meeting->participants()->count(),
            ],
        ], 201);
    }
}
