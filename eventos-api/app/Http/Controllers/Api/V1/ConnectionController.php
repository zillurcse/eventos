<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\Participation;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Participation-to-participation networking (architecture §6.5). Acts as the
 * resolved participation (ResolveParticipant middleware).
 */
class ConnectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $me = $request->attributes->get('participation_id');

        $connections = Connection::where('requester_participation_id', $me)
            ->orWhere('addressee_participation_id', $me)
            ->latest('id')
            ->get()
            ->map(fn (Connection $c) => [
                'id' => $c->id,
                'status' => $c->status,
                'direction' => $c->requester_participation_id == $me ? 'outgoing' : 'incoming',
            ]);

        return response()->json(['data' => $connections]);
    }

    public function store(Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $me = $request->attributes->get('participation_id');

        $data = $request->validate([
            'to' => ['required', 'string'], // addressee participation uuid
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $addressee = Participation::where('uuid', $data['to'])->where('event_id', $eventId)->firstOrFail();
        abort_if($addressee->id == $me, 422, 'You cannot connect with yourself.');

        $connection = Connection::firstOrCreate(
            ['event_id' => $eventId, 'requester_participation_id' => $me, 'addressee_participation_id' => $addressee->id],
            ['status' => 'pending', 'message' => $data['message'] ?? null],
        );

        return response()->json(['data' => ['id' => $connection->id, 'status' => $connection->status]], 201);
    }

    public function respond(string $event, int $connection, Request $request, NotificationService $notifications): JsonResponse
    {
        $me = $request->attributes->get('participation_id');
        $data = $request->validate(['action' => ['required', 'in:accept,decline,block']]);

        $record = Connection::where('id', $connection)
            ->where('addressee_participation_id', $me)
            ->firstOrFail();

        $record->update([
            'status' => ['accept' => 'accepted', 'decline' => 'declined', 'block' => 'blocked'][$data['action']],
            'responded_at' => now(),
        ]);

        if ($record->status === 'accepted') {
            $notifications->notify(
                'participation', $record->requester_participation_id,
                $record->organization_id, $record->event_id,
                'connection.accepted',
                ['title' => 'Connection accepted', 'body' => 'Your connection request was accepted.'],
            );
        }

        return response()->json(['data' => ['id' => $record->id, 'status' => $record->status]]);
    }
}
