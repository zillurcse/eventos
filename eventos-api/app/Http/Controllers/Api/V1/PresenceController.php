<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

/**
 * Lightweight online presence for attendees. The event app heartbeats
 * POST /events/{event}/presence every ~60s while a signed-in tab is open;
 * each ping refreshes a per-participation Redis key with a short TTL, so
 * "online" simply means "pinged within the last 2½ minutes". O(1) per user
 * and no websocket fan-out, so it stays cheap at very large attendee counts.
 * Readers (e.g. the delegate directory) MGET the keys for the rows they show.
 */
class PresenceController extends Controller
{
    /** Key TTL in seconds — survives ~2 missed 60s heartbeats. */
    public const TTL = 150;

    public static function key(int|string $eventId, int|string $participationId): string
    {
        return "presence:{$eventId}:{$participationId}";
    }

    public function ping(Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $me = $request->attributes->get('participation_id');

        try {
            Redis::setex(self::key($eventId, $me), self::TTL, '1');
        } catch (\Throwable) {
            // Presence is best-effort — never fail the request over it.
        }

        return response()->json(['data' => ['online' => true, 'ttl' => self::TTL]]);
    }
}
