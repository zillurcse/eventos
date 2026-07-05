<?php

namespace App\Services\BreakoutRoom\Providers;

use App\Models\BreakoutRoom;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Self-hosted LiveKit (WebRTC SFU) provider.
 *
 * Join = a short-lived HS256 JWT ("access token") minted here with a `video`
 * grant derived from the participant's room role, plus the ws:// server URL.
 * The browser SDK connects with those two values — no per-user secret leaves
 * the server. Admin actions (kick / lock) go over LiveKit's RoomService Twirp
 * RPC, authorized by a token carrying the `roomAdmin` grant.
 *
 * Grants by role (see architecture §4 matrix):
 *   host / moderator → publish + subscribe + data + roomAdmin
 *   speaker          → publish + subscribe + data
 *   attendee / guest → subscribe + data only (no camera/mic publish)
 *
 * Token minting is a plain HS256 JWT (base64url header.payload.HMAC), so no
 * extra Composer dependency is required.
 */
class LiveKitProvider implements MeetingProvider
{
    private const PUBLISH_ROLES = ['host', 'moderator', 'speaker'];
    private const ADMIN_ROLES = ['host', 'moderator'];

    public function joinConfig(BreakoutRoom $room, array $participant): array
    {
        return $this->joinConfigForRoom($this->roomName($room), $participant);
    }

    /**
     * Mint a join config for an arbitrary LiveKit room name — used by features
     * that aren't backed by a BreakoutRoom row (e.g. networking-lounge tables).
     * The caller owns the room-name scheme; keep it tenant-safe (no BIGINT ids).
     */
    public function joinConfigForRoom(string $roomName, array $participant): array
    {
        $role = $participant['role'] ?? 'attendee';
        // Publish grant defaults to the role matrix, but the caller may widen it
        // for participatory room types (attendees who get their own mic/camera).
        $canPublish = $participant['canPublish'] ?? in_array($role, self::PUBLISH_ROLES, true);
        $isAdmin = in_array($role, self::ADMIN_ROLES, true);

        $grant = [
            'room' => $roomName,
            'roomJoin' => true,
            'canPublish' => $canPublish,
            'canPublishData' => true,   // chat / reactions / raise-hand ride the data channel
            'canSubscribe' => true,
            'roomAdmin' => $isAdmin,
        ];

        return [
            'provider' => 'webrtc',
            'url' => (string) config('services.livekit.url'),
            'room' => $roomName,
            'token' => $this->mintToken(
                identity: $participant['identity'],
                name: $participant['name'] ?? $participant['identity'],
                grant: $grant,
                metadata: json_encode(array_filter([
                    'role' => $role,
                    'avatar_url' => $participant['avatar_url'] ?? null,
                ])),
            ),
        ];
    }

    /**
     * Live participant counts keyed by room name, from a single ListRooms RPC.
     * Returns [] when LiveKit is unreachable so callers degrade gracefully
     * (tables render as empty/available rather than erroring).
     */
    public function roomOccupancy(): array
    {
        try {
            $out = [];
            foreach ($this->rpc('ListRooms', [])['rooms'] ?? [] as $r) {
                $out[$r['name'] ?? ''] = (int) ($r['num_participants'] ?? 0);
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** Live participant count for one room (0 when unreachable). */
    public function participantCount(string $roomName): int
    {
        try {
            return count($this->rpc('ListParticipants', ['room' => $roomName])['participants'] ?? []);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Live occupants of one room as [{ identity, name, avatar_url }] — the seat
     * roster for the lounge table card. avatar_url is pulled from the join-time
     * participant metadata. Returns [] when the room is empty or unreachable.
     */
    public function participants(string $roomName): array
    {
        try {
            return collect($this->rpc('ListParticipants', ['room' => $roomName])['participants'] ?? [])
                ->map(function ($p) {
                    $meta = json_decode($p['metadata'] ?? '', true);

                    return [
                        'identity' => $p['identity'] ?? '',
                        'name' => $p['name'] ?? ($p['identity'] ?? 'Guest'),
                        'avatar_url' => is_array($meta) ? ($meta['avatar_url'] ?? null) : null,
                    ];
                })
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function removeParticipant(BreakoutRoom $room, string $identity): void
    {
        $this->rpc('RemoveParticipant', [
            'room' => $this->roomName($room),
            'identity' => $identity,
        ]);
    }

    public function setLocked(BreakoutRoom $room, bool $locked): void
    {
        // LiveKit has no first-class "lock"; we carry it in room metadata and the
        // join path (RoomAccessService) refuses new tokens while locked.
        $this->rpc('UpdateRoomMetadata', [
            'room' => $this->roomName($room),
            'metadata' => json_encode(['locked' => $locked]),
        ]);
    }

    public function startRecording(BreakoutRoom $room): string
    {
        // Room-composite egress to MinIO/S3. Wire the egress request once the
        // recordings table + storage creds are finalized (architecture §13.3).
        throw new RuntimeException('LiveKit egress recording not yet configured.');
    }

    public function stopRecording(BreakoutRoom $room, string $recordingId): void
    {
        throw new RuntimeException('LiveKit egress recording not yet configured.');
    }

    /** Stable LiveKit room name — the tenant-safe public uuid, never the BIGINT id. */
    private function roomName(BreakoutRoom $room): string
    {
        return 'room_'.$room->uuid;
    }

    /**
     * Mint a LiveKit access token (HS256 JWT). `iss` = API key, `sub`/identity =
     * participant, `video` = the grant. Signed with the shared API secret.
     */
    private function mintToken(string $identity, string $name, array $grant, ?string $metadata = null): string
    {
        $key = (string) config('services.livekit.api_key');
        $secret = (string) config('services.livekit.api_secret');
        $ttl = (int) config('services.livekit.token_ttl', 3600);
        $now = time();

        $payload = [
            'iss' => $key,
            'sub' => $identity,
            'nbf' => $now,
            'iat' => $now,
            'exp' => $now + $ttl,
            'name' => $name,
            'video' => $grant,
        ];
        if ($metadata !== null) {
            $payload['metadata'] = $metadata;
        }

        $segments = [
            $this->b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])),
            $this->b64(json_encode($payload)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = $this->b64($signature);

        return implode('.', $segments);
    }

    /** Call a LiveKit RoomService Twirp method with an admin-scoped token. */
    private function rpc(string $method, array $body): array
    {
        $token = $this->mintToken(
            identity: 'server',
            name: 'server',
            // roomAdmin authorizes per-room ops (participants, metadata); roomList
            // authorizes the room-wide ListRooms occupancy read.
            grant: ['roomAdmin' => true, 'roomList' => true, 'room' => $body['room'] ?? ''],
        );

        // Twirp requires a JSON *object* body; an empty PHP array encodes to `[]`
        // (a JSON array) which LiveKit rejects as malformed — coerce to `{}`.
        $response = Http::withToken($token)
            ->acceptJson()
            ->withBody(json_encode($body ?: (object) []), 'application/json')
            ->post(rtrim((string) config('services.livekit.host'), '/')
                .'/twirp/livekit.RoomService/'.$method);

        if ($response->failed()) {
            throw new RuntimeException("LiveKit RPC {$method} failed: ".$response->body());
        }

        return $response->json() ?? [];
    }

    /** URL-safe base64 without padding, per JWT spec. */
    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
