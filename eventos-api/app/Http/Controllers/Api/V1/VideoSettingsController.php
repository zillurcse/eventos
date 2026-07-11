<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSetting;
use App\Support\Agora\AccessToken2;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Settings › Video — the event's own video-provider credentials.
 *
 * Two providers, each needing a server-held secret so the API can sign a
 * per-viewer token that says who may present and who may only watch:
 *
 *   Jitsi — a signing key (JaaS RS256, or a self-hosted Prosody HS256). Without
 *           one, a public Jitsi refuses to start the room and attendees are
 *           stuck on "waiting for a moderator".
 *   Agora — an App Certificate. Without one the Web SDK cannot join a channel.
 *
 * Which one a session uses is chosen per session (Stream › Who will host), so
 * an event can hold both sets at once. Secrets are encrypted at rest and never
 * returned to the client — we only report whether they are present. The
 * platform .env stays as the fallback for events that leave this blank.
 */
class VideoSettingsController extends Controller
{
    public function show(string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $video = EventSetting::firstOrCreate(['event_id' => $event->id])->video ?? [];

        return response()->json(['data' => $this->payload($video)]);
    }

    public function update(string $uuid, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'jitsi' => ['sometimes', 'array'],
            'jitsi.domain' => ['nullable', 'string', 'max:200'],
            'jitsi.app_id' => ['nullable', 'string', 'max:200'],
            'jitsi.kid' => ['nullable', 'string', 'max:200'],
            'jitsi.app_secret' => ['nullable', 'string', 'max:500'],
            'jitsi.private_key' => ['nullable', 'string', 'max:8000'],
            'jitsi.clear' => ['nullable', 'boolean'],

            'agora' => ['sometimes', 'array'],
            // Both are 32-char hex; reject a mis-paste now rather than failing
            // to sign a token at the start of a live session.
            'agora.app_id' => ['nullable', 'string', 'regex:/^[0-9a-fA-F]{32}$/'],
            'agora.app_certificate' => ['nullable', 'string', 'regex:/^[0-9a-fA-F]{32}$/'],
            'agora.clear' => ['nullable', 'boolean'],
        ], [
            'agora.app_id.regex' => 'An Agora App ID is a 32-character hex string.',
            'agora.app_certificate.regex' => 'An Agora App Certificate is a 32-character hex string.',
        ]);

        $settings = EventSetting::firstOrCreate(['event_id' => $event->id]);
        $video = $settings->video ?? [];

        if ($request->has('jitsi')) {
            $video['jitsi'] = $this->mergeJitsi($video['jitsi'] ?? [], $data['jitsi'] ?? []);
        }
        if ($request->has('agora')) {
            $video['agora'] = $this->mergeAgora($video['agora'] ?? [], $data['agora'] ?? []);
        }

        $settings->video = $video;
        $settings->save();

        return response()->json(['data' => $this->payload($video)]);
    }

    // ── Merge helpers ───────────────────────────────────────────────────────
    private function mergeJitsi(array $current, array $in): array
    {
        foreach (['domain', 'app_id', 'kid'] as $key) {
            if (array_key_exists($key, $in)) {
                $current[$key] = $in[$key] !== '' ? $in[$key] : null;
            }
        }

        // Only overwrite a secret when a new value actually arrives, so the form
        // can be re-saved without re-pasting the key every time.
        foreach (['app_secret', 'private_key'] as $secret) {
            if (! empty($in[$secret])) {
                $current[$secret] = Crypt::encryptString(trim($in[$secret]));
            }
        }

        if (! empty($in['clear'])) {
            $current = [];
        }

        return $current;
    }

    private function mergeAgora(array $current, array $in): array
    {
        if (array_key_exists('app_id', $in)) {
            $current['app_id'] = $in['app_id'] !== '' ? trim($in['app_id']) : null;
        }
        if (! empty($in['app_certificate'])) {
            $current['app_certificate'] = Crypt::encryptString(trim($in['app_certificate']));
        }
        if (! empty($in['clear'])) {
            $current = [];
        }

        return $current;
    }

    // ── Projection ──────────────────────────────────────────────────────────
    /** Never leak the stored secrets — say only whether they exist. */
    private function payload(array $video): array
    {
        $j = $video['jitsi'] ?? [];
        $a = $video['agora'] ?? [];

        return [
            'jitsi' => [
                'domain' => $j['domain'] ?? null,
                'app_id' => $j['app_id'] ?? null,
                'kid' => $j['kid'] ?? null,
                'has_private_key' => ! empty($j['private_key']),
                'has_app_secret' => ! empty($j['app_secret']),
                'configured' => ! empty($j['app_id']) && (! empty($j['private_key']) || ! empty($j['app_secret'])),
            ],
            'agora' => [
                'app_id' => $a['app_id'] ?? null,
                'has_certificate' => ! empty($a['app_certificate']),
                'configured' => ! empty($a['app_id']) && ! empty($a['app_certificate']),
            ],
        ];
    }

    // ── Resolution for the token endpoints ──────────────────────────────────
    private static function video(int $eventId): array
    {
        $video = EventSetting::where('event_id', $eventId)->value('video') ?? [];

        return is_string($video) ? (json_decode($video, true) ?: []) : $video;
    }

    /** Decrypt, tolerating a rotated APP_KEY rather than exploding mid-session. */
    private static function decrypt(?string $v): ?string
    {
        if (! $v) {
            return null;
        }
        try {
            return Crypt::decryptString($v);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The event's Jitsi config for token signing, falling back to the platform
     * .env. Used by SessionEngagementController::jitsiToken.
     */
    public static function jitsiConfigFor(int $eventId): array
    {
        $platform = config('services.jitsi', []);
        $j = self::video($eventId)['jitsi'] ?? [];

        if (empty($j['app_id'])) {
            return $platform;
        }

        return [
            'domain' => $j['domain'] ?: ($platform['domain'] ?? 'meet.jit.si'),
            'app_id' => $j['app_id'],
            'kid' => $j['kid'] ?? null,
            'private_key' => self::decrypt($j['private_key'] ?? null),
            'app_secret' => self::decrypt($j['app_secret'] ?? null),
            'token_ttl' => $platform['token_ttl'] ?? 7200,
        ];
    }

    /**
     * The event's Agora config, falling back to the platform .env. Used by
     * SessionEngagementController::agoraToken.
     */
    public static function agoraConfigFor(int $eventId): array
    {
        $platform = config('services.agora', []);
        $a = self::video($eventId)['agora'] ?? [];

        if (empty($a['app_id'])) {
            return $platform;
        }

        return [
            'app_id' => $a['app_id'],
            'app_certificate' => self::decrypt($a['app_certificate'] ?? null),
            'token_ttl' => $platform['token_ttl'] ?? 7200,
        ];
    }

    /** Convenience for the front end: is Agora usable for this event at all? */
    public static function agoraReady(int $eventId): bool
    {
        $cfg = self::agoraConfigFor($eventId);

        return AccessToken2::looksValid($cfg['app_id'] ?? null)
            && AccessToken2::looksValid($cfg['app_certificate'] ?? null);
    }
}
