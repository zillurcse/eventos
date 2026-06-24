<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ParticipationResource;
use App\Models\CheckIn;
use App\Models\Participation;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * QR/badge scan (architecture §6.4). `code` is a ticket qr_token (paid) or a
 * participation uuid (free). RLS keeps lookups within the current org.
 */
class CheckInController extends Controller
{
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'station_id' => ['nullable', 'integer', 'exists:check_in_stations,id'],
            'session_id' => ['nullable', 'integer', 'exists:sessions,id'],
            'direction' => ['nullable', 'in:in,out'],
        ]);

        $participation = $this->resolve($data['code']);
        abort_unless($participation, 404, 'No matching ticket or participation.');

        $direction = $data['direction'] ?? 'in';

        $checkIn = CheckIn::create([
            'event_id' => $participation->event_id,
            'participation_id' => $participation->id,
            'session_id' => $data['session_id'] ?? null,
            'station_id' => $data['station_id'] ?? null,
            'scanned_by' => $request->user()->id,
            'direction' => $direction,
            'scanned_at' => now(),
        ]);

        if ($direction === 'in') {
            $participation->update(['checked_in_at' => now(), 'status' => 'checked_in']);
        }

        return response()->json([
            'participation' => new ParticipationResource($participation->fresh()->load('contact')),
            'scanned_at' => $checkIn->scanned_at?->toIso8601String(),
            'direction' => $checkIn->direction,
        ]);
    }

    protected function resolve(string $code): ?Participation
    {
        $ticket = Ticket::where('qr_token', $code)->first();

        if ($ticket && $ticket->participation_id) {
            return Participation::find($ticket->participation_id);
        }

        return Participation::where('uuid', $code)->first();
    }
}
