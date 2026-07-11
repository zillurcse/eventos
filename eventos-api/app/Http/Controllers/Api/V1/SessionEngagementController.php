<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Participation;
use App\Models\Session;
use App\Models\SessionMessage;
use App\Models\SessionMute;
use App\Models\SessionPoll;
use App\Models\SessionPollVote;
use App\Support\Agora\AccessToken2;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Attendee-facing live-session engagement panel (watch page): group Chat, Q&A,
 * live Polls and the Attendees list. Runs under the `participant` middleware, so
 * event_id / participation_id are on the request and the org GUC is set. Realtime
 * is client polling (no websocket fan-out) — cheap and simple at any scale.
 *
 * Moderation: the session's host — anyone on the session_speaker pivot, plus
 * event staff (Session::isModeratedBy) — can hide, pin, answer or delete any
 * message, run the poll lifecycle, and mute a participant for the session. What
 * an ordinary attendee is served is always the moderated view; the host gets the
 * same rows plus the hidden/pending ones, flagged, so they can act on them.
 * Every index response carries meta.can_moderate so the client knows which UI
 * to render — but the server never trusts that, it re-checks on each write.
 */
class SessionEngagementController extends Controller
{
    // ── Chat ────────────────────────────────────────────────────────────────
    public function chatIndex(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $pid = $this->participationId($request);

        $rows = SessionMessage::where('session_id', $s->id)
            ->where('kind', 'chat')
            ->when(! $this->canModerate($request), fn ($q) => $q->visibleTo($pid))
            ->with('participation.contact')
            ->orderByDesc('id')
            ->limit(120)
            ->get()
            ->reverse()
            ->values();

        return $this->messageList($rows, $request, $s);
    }

    public function chatSend(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $this->abortIfMuted($request, $s);

        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $m = SessionMessage::create([
            'event_id' => $request->attributes->get('event_id'),
            'session_id' => $s->id,
            'participation_id' => $this->participationId($request),
            'kind' => 'chat',
            'status' => SessionMessage::STATUS_PUBLISHED,
            'body' => trim($data['body']),
        ])->load('participation.contact');

        return response()->json(['data' => $this->message($m, $request)], 201);
    }

    // ── Q&A ─────────────────────────────────────────────────────────────────
    public function questionIndex(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $pid = $this->participationId($request);

        $rows = SessionMessage::where('session_id', $s->id)
            ->where('kind', 'question')
            ->when(! $this->canModerate($request), fn ($q) => $q->visibleTo($pid))
            ->with('participation.contact')
            ->orderByDesc('is_pinned')      // host's pick rides at the top
            ->orderBy('is_answered')        // still-open questions before answered ones
            ->orderByDesc('upvotes')
            ->orderByDesc('id')
            ->limit(150)
            ->get();

        return $this->messageList($rows, $request, $s);
    }

    public function questionAsk(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $this->abortIfMuted($request, $s);

        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);

        // With pre-moderation on, the question waits for the host; the asker
        // still sees it (scopeVisibleTo) marked as pending.
        $m = SessionMessage::create([
            'event_id' => $request->attributes->get('event_id'),
            'session_id' => $s->id,
            'participation_id' => $this->participationId($request),
            'kind' => 'question',
            'status' => $s->qaModerated() ? SessionMessage::STATUS_PENDING : SessionMessage::STATUS_PUBLISHED,
            'body' => trim($data['body']),
            'meta' => ['voters' => []],
        ])->load('participation.contact');

        return response()->json(['data' => $this->message($m, $request)], 201);
    }

    public function questionUpvote(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $pid = $this->participationId($request);

        $m = SessionMessage::where('session_id', $s->id)
            ->where('kind', 'question')
            ->findOrFail((int) $request->route('message'));

        // No brigading a question that isn't public yet.
        if ($m->status !== SessionMessage::STATUS_PUBLISHED || $m->is_hidden) {
            return response()->json(['message' => 'This question is not open for votes.'], 422);
        }

        $voters = collect($m->meta['voters'] ?? []);
        $voters = $voters->contains($pid)
            ? $voters->reject(fn ($v) => (int) $v === $pid)->values()
            : $voters->push($pid);

        $m->meta = array_merge($m->meta ?? [], ['voters' => $voters->all()]);
        $m->upvotes = $voters->count();
        $m->save();

        return response()->json(['data' => ['upvotes' => $m->upvotes, 'voted' => $voters->contains($pid)]]);
    }

    // ── Message moderation (host) ───────────────────────────────────────────
    /**
     * Hide/unhide, pin/unpin, mark answered, or approve/reject a pending
     * question. Host only — one endpoint for both chat and Q&A, since they're
     * the same row.
     */
    public function messageModerate(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $this->abortUnlessModerator($request);

        $data = $request->validate([
            'is_hidden' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_answered' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:published,pending,rejected'],
        ]);

        $m = SessionMessage::where('session_id', $s->id)
            ->findOrFail((int) $request->route('message'));

        if (array_key_exists('is_hidden', $data) && $data['is_hidden'] !== null) {
            $m->is_hidden = $data['is_hidden'];
        }
        if (array_key_exists('is_pinned', $data) && $data['is_pinned'] !== null) {
            $m->is_pinned = $data['is_pinned'];
        }
        if (array_key_exists('is_answered', $data) && $data['is_answered'] !== null) {
            $m->is_answered = $data['is_answered'];
            $m->answered_at = $data['is_answered'] ? now() : null;
        }
        if (! empty($data['status'])) {
            $m->status = $data['status'];
        }

        $m->moderated_by = $this->participationId($request);
        $m->moderated_at = now();
        $m->save();

        return response()->json(['data' => $this->message($m->load('participation.contact'), $request)]);
    }

    /** Delete a message: the host can remove anyone's, an author their own. */
    public function messageDestroy(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $pid = $this->participationId($request);

        $m = SessionMessage::where('session_id', $s->id)
            ->findOrFail((int) $request->route('message'));

        abort_unless(
            $this->canModerate($request) || (int) $m->participation_id === $pid,
            403,
            'You can only delete your own messages.',
        );

        $m->moderated_by = $pid;
        $m->moderated_at = now();
        $m->save();
        $m->delete(); // soft — recoverable, and the row survives for audit

        return response()->json(['status' => 'success']);
    }

    // ── Polls ───────────────────────────────────────────────────────────────
    public function pollIndex(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $host = $this->canModerate($request);
        $pid = $this->participationId($request);

        $polls = SessionPoll::where('session_id', $s->id)
            ->when(! $host, fn ($q) => $q->whereIn('status', [SessionPoll::STATUS_LIVE, SessionPoll::STATUS_CLOSED]))
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $this->pollPayload($polls, $pid, $host),
            'meta' => $this->meta($request, $s),
        ]);
    }

    public function pollVote(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $pid = $this->participationId($request);

        $p = SessionPoll::where('session_id', $s->id)->findOrFail((int) $request->route('poll'));
        if (! $p->isLive()) {
            return response()->json(['message' => 'This poll is not open for voting.'], 422);
        }

        $data = $request->validate(['option_id' => ['required', 'string', 'max:20']]);
        if (! collect($p->options)->pluck('id')->contains($data['option_id'])) {
            return response()->json(['message' => 'Invalid option.'], 422);
        }

        // Unique on (poll, participation): a vote can be changed, never doubled.
        SessionPollVote::updateOrCreate(
            ['session_poll_id' => $p->id, 'participation_id' => $pid],
            ['option_id' => $data['option_id']],
        );

        return $this->pollIndex($request);
    }

    /** Host authors a poll live, straight from the watch page. */
    public function pollStore(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $this->abortUnlessModerator($request);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:300'],
            'options' => ['required', 'array', 'min:2', 'max:8'],
            'options.*' => ['required', 'string', 'max:200'],
            'status' => ['nullable', 'in:draft,live'],
            'show_results' => ['nullable', 'boolean'],
        ]);

        $status = $data['status'] ?? SessionPoll::STATUS_LIVE;

        SessionPoll::create([
            'event_id' => $request->attributes->get('event_id'),
            'session_id' => $s->id,
            'question' => trim($data['question']),
            'options' => $this->normalizeOptions($data['options']),
            'status' => $status,
            'show_results' => $data['show_results'] ?? true,
            'published_at' => $status === SessionPoll::STATUS_LIVE ? now() : null,
            'created_by' => $request->user()?->id,
            'created_by_participation_id' => $this->participationId($request),
        ]);

        return $this->pollIndex($request);
    }

    /** Host drives the lifecycle: launch a draft, close voting, reopen, reveal results. */
    public function pollUpdate(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $this->abortUnlessModerator($request);

        $data = $request->validate([
            'status' => ['nullable', 'in:draft,live,closed'],
            'show_results' => ['nullable', 'boolean'],
            'question' => ['nullable', 'string', 'max:300'],
            'options' => ['nullable', 'array', 'min:2', 'max:8'],
            'options.*' => ['required', 'string', 'max:200'],
        ]);

        $p = SessionPoll::where('session_id', $s->id)->findOrFail((int) $request->route('poll'));

        $this->applyPollChanges($p, $data);

        return $this->pollIndex($request);
    }

    public function pollDestroy(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $this->abortUnlessModerator($request);

        SessionPoll::where('session_id', $s->id)
            ->findOrFail((int) $request->route('poll'))
            ->delete();

        return $this->pollIndex($request);
    }

    // ── Attendees ───────────────────────────────────────────────────────────
    public function attendees(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $eventId = (int) $request->attributes->get('event_id');

        $speakerIds = $s->speakers()->pluck('participations.id')->all();
        $mutedIds = SessionMute::where('session_id', $s->id)->pluck('participation_id')->all();

        $people = Participation::where('event_id', $eventId)
            ->whereIn('role', ['attendee', 'speaker', 'staff'])
            ->with('contact')
            ->limit(200)
            ->get();

        // Online = a fresh presence key in Redis. Best-effort: if Redis is down
        // the list still renders, just without the green dots.
        $online = [];
        try {
            $keys = $people->map(fn ($p) => PresenceController::key($eventId, $p->id))->all();
            if ($keys) {
                foreach (Redis::mget($keys) as $i => $v) {
                    if ($v !== null) {
                        $online[$people[$i]->id] = true;
                    }
                }
            }
        } catch (\Throwable) {
            // Presence is best-effort.
        }

        $list = $people->map(fn (Participation $p) => [
            'id' => $p->uuid,
            'name' => $p->contact?->fullName() ?: 'Attendee',
            'image_url' => $p->profile_data['image_url'] ?? null,
            'headline' => $p->profile_data['designation'] ?? ($p->profile_data['company'] ?? null),
            'is_speaker' => in_array($p->id, $speakerIds, true),
            'is_muted' => in_array($p->id, $mutedIds, true),
            'online' => isset($online[$p->id]),
        ])->sortByDesc(fn ($x) => [$x['online'], $x['is_speaker']])->values();

        return response()->json([
            'data' => $list,
            'meta' => array_merge($this->meta($request, $s), [
                'online' => count($online),
                'total' => $people->count(),
            ]),
        ]);
    }

    /** Silence a participant for this session (host only). */
    public function muteStore(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $this->abortUnlessModerator($request);

        $data = $request->validate([
            'participation' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $target = Participation::where('event_id', $request->attributes->get('event_id'))
            ->where('uuid', $data['participation'])
            ->firstOrFail();

        // A host muting a host would be a foot-gun during a live panel.
        abort_if($s->isModeratedBy($target), 422, 'You cannot mute a session host.');

        SessionMute::updateOrCreate(
            ['session_id' => $s->id, 'participation_id' => $target->id],
            [
                'event_id' => $s->event_id,
                'muted_by' => $this->participationId($request),
                'reason' => $data['reason'] ?? null,
            ],
        );

        return response()->json(['status' => 'success']);
    }

    public function muteDestroy(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $this->abortUnlessModerator($request);

        $target = Participation::where('event_id', $request->attributes->get('event_id'))
            ->where('uuid', (string) $request->route('participation'))
            ->firstOrFail();

        SessionMute::where('session_id', $s->id)
            ->where('participation_id', $target->id)
            ->delete();

        return response()->json(['status' => 'success']);
    }

    // ── Jitsi ───────────────────────────────────────────────────────────────
    /**
     * Join details for an embedded Jitsi session: the room, and a JWT that says
     * who this viewer is and whether they may moderate.
     *
     * The public meet.jit.si refuses to start a room until an *authenticated*
     * moderator arrives, which strands attendees on "waiting for a moderator".
     * So on a configured Jitsi we mint a token here: the session host (same
     * isModeratedBy rule as chat/Q&A) gets moderator, everyone else is a guest
     * who can watch without one. With no signing key configured we return no
     * token and the client joins anonymously — fine for local dev, not for a
     * real event.
     */
    public function jitsiToken(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $p = $this->participation($request);
        $isHost = $this->canModerate($request);

        // The organizer's own JaaS/Jitsi account (Settings › Video), falling
        // back to the platform's .env when the event hasn't set one.
        $cfg = VideoSettingsController::jitsiConfigFor((int) $s->event_id);
        $domain = (string) (($cfg['domain'] ?? null) ?: 'meet.jit.si');

        // Prefer the organizer's room/link; otherwise a stable per-session room.
        $raw = trim((string) ($s->meta['stream_link'] ?? ''));
        $room = 'expouse-'.$s->uuid;
        if ($raw !== '') {
            if (preg_match('#^https?://([^/]+)/(.+)$#i', $raw, $m)) {
                $domain = $m[1];
                $room = rawurldecode(rtrim($m[2], '/'));
            } elseif (! str_contains($raw, '/')) {
                $room = $raw;
            }
        }

        $name = $p?->contact?->fullName() ?: 'Guest';
        $email = $p?->contact?->email;
        $avatar = $p?->profile_data['image_url'] ?? null;

        return response()->json(['data' => [
            'domain' => $domain,
            'room' => $this->jitsiRoom($room, $cfg),
            'jwt' => $this->jitsiJwt($room, $isHost, $name, $email, $avatar, $cfg),
            'is_moderator' => $isHost,
            'display_name' => $name,
        ]]);
    }

    /** JaaS namespaces every room under the tenant: "<app_id>/<room>". */
    private function jitsiRoom(string $room, array $cfg): string
    {
        $appId = (string) ($cfg['app_id'] ?? '');

        return str_starts_with($appId, 'vpaas-magic-cookie-') ? $appId.'/'.$room : $room;
    }

    /** Sign a Jitsi JWT — RS256 for JaaS, HS256 for a self-hosted Prosody. */
    private function jitsiJwt(string $room, bool $isHost, string $name, ?string $email, ?string $avatar, array $cfg): ?string
    {
        $appId = $cfg['app_id'] ?? null;
        $secret = $cfg['app_secret'] ?? null;
        $kid = $cfg['kid'] ?? null;
        $key = $cfg['private_key'] ?? null;

        if (! $appId || (! $secret && ! $key)) {
            return null; // unconfigured → anonymous join (dev only)
        }

        $now = time();
        $user = array_filter([
            'name' => $name,
            'email' => $email,
            'avatar' => $avatar,
            'moderator' => $isHost ? 'true' : 'false',
        ], fn ($v) => $v !== null);

        $payload = [
            'aud' => $key ? 'jitsi' : $appId,
            'iss' => $key ? 'chat' : $appId,
            'sub' => $key ? $appId : ($cfg['domain'] ?: 'meet.jit.si'),
            'room' => $room,
            'exp' => $now + max(300, (int) ($cfg['token_ttl'] ?? 7200)),
            'nbf' => $now - 30,
            'context' => [
                'user' => $user,
                'features' => [
                    // Only the host may broadcast or record.
                    'livestreaming' => $isHost ? 'true' : 'false',
                    'recording' => $isHost ? 'true' : 'false',
                    'moderation' => $isHost ? 'true' : 'false',
                ],
            ],
        ];

        $b64 = fn (string $d): string => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');

        if ($key) {
            $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $kid];
            $signing = $b64(json_encode($header)).'.'.$b64(json_encode($payload));
            $signature = '';
            if (! openssl_sign($signing, $signature, $key, OPENSSL_ALGO_SHA256)) {
                return null;
            }

            return $signing.'.'.$b64($signature);
        }

        $signing = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']))
            .'.'.$b64(json_encode($payload));

        return $signing.'.'.$b64(hash_hmac('sha256', $signing, (string) $secret, true));
    }

    // ── Agora ───────────────────────────────────────────────────────────────
    /**
     * Join details for an embedded Agora session: the channel, this viewer's
     * uid, and a short-lived AccessToken2.
     *
     * Agora's Web SDK refuses to join without a token signed by the App
     * Certificate, which must never reach the browser. The role is decided here
     * and baked into the token's privileges — the session host (same
     * isModeratedBy rule as chat/Q&A) may publish audio and video; everyone else
     * gets join-only, so an attendee cannot start broadcasting even if they
     * tamper with the client.
     */
    public function agoraToken(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $isHost = $this->canModerate($request);

        $cfg = VideoSettingsController::agoraConfigFor((int) $s->event_id);
        $appId = $cfg['app_id'] ?? null;
        $cert = $cfg['app_certificate'] ?? null;

        if (! AccessToken2::looksValid($appId) || ! AccessToken2::looksValid($cert)) {
            return response()->json([
                'message' => 'Agora is not configured for this event. Add an App ID and App Certificate in Settings › Video.',
            ], 503);
        }

        // The organizer may pin a channel name; otherwise one per session.
        // Agora channel names are capped at 64 chars and have a limited alphabet.
        $raw = trim((string) ($s->meta['stream_link'] ?? ''));
        $channel = $raw !== ''
            ? substr(preg_replace('/[^A-Za-z0-9 !#$%&()+\-:;<=>.?@\[\]^_{|}~,]/', '', $raw), 0, 64)
            : 'expouse-'.$s->uuid;

        // A stable numeric uid per participation, so a reconnect keeps its seat
        // and the client can tell the host's stream from an attendee's.
        $uid = $this->participationId($request) % 1000000000 ?: 1;
        $ttl = max(300, (int) ($cfg['token_ttl'] ?? 7200));

        // $ttl is a lifetime in seconds, not a deadline — Agora derives the
        // deadline from the token's own issue timestamp.
        $token = (new AccessToken2($appId, $cert, $channel, (string) $uid, $ttl))
            ->grant(AccessToken2::PRIVILEGE_JOIN_CHANNEL, $ttl);

        if ($isHost) {
            $token->grant(AccessToken2::PRIVILEGE_PUBLISH_AUDIO, $ttl)
                ->grant(AccessToken2::PRIVILEGE_PUBLISH_VIDEO, $ttl)
                ->grant(AccessToken2::PRIVILEGE_PUBLISH_DATA, $ttl);
        }

        $built = $token->build();
        if ($built === null) {
            return response()->json(['message' => 'Could not sign the Agora token.'], 500);
        }

        return response()->json(['data' => [
            'app_id' => $appId,
            'channel' => $channel,
            'uid' => $uid,
            'token' => $built,
            'role' => $isHost ? 'host' : 'audience',
            'expires_in' => $ttl,
        ]]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────
    /** Resolve a session by uuid within the current event; 404 otherwise. */
    private function session(Request $request): Session
    {
        // Read the session uuid from the route by name: with `events/{event}`
        // as the group prefix, positional controller args would receive the
        // event uuid, so we look it up explicitly (matches the codebase pattern).
        return once(fn () => Session::where('event_id', $request->attributes->get('event_id'))
            ->where('uuid', (string) $request->route('sessionUuid'))
            ->firstOrFail());
    }

    private function participationId(Request $request): int
    {
        return (int) $request->attributes->get('participation_id');
    }

    private function participation(Request $request): ?Participation
    {
        return once(fn () => Participation::find($this->participationId($request)));
    }

    /** Is the caller a host of this session? Re-checked on every write. */
    private function canModerate(Request $request): bool
    {
        return once(fn () => $this->session($request)->isModeratedBy($this->participation($request)));
    }

    private function abortUnlessModerator(Request $request): void
    {
        abort_unless($this->canModerate($request), 403, 'Only the session host can do that.');
    }

    /** A muted participant may watch and vote, but not post. */
    private function abortIfMuted(Request $request, Session $s): void
    {
        $muted = SessionMute::where('session_id', $s->id)
            ->where('participation_id', $this->participationId($request))
            ->exists();

        abort_if($muted, 403, 'The host has muted you for this session.');
    }

    /** Capabilities the panel needs to decide what to render. */
    private function meta(Request $request, Session $s): array
    {
        $host = $this->canModerate($request);

        return [
            'can_moderate' => $host,
            'is_muted' => SessionMute::where('session_id', $s->id)
                ->where('participation_id', $this->participationId($request))
                ->exists(),
            'qa_moderation' => $s->qaModerated(),
            'pending_count' => $host
                ? SessionMessage::where('session_id', $s->id)
                    ->where('kind', 'question')
                    ->where('status', SessionMessage::STATUS_PENDING)
                    ->count()
                : 0,
        ];
    }

    private function messageList($rows, Request $request, Session $s): JsonResponse
    {
        return response()->json([
            'data' => $rows->map(fn (SessionMessage $m) => $this->message($m, $request)),
            'meta' => $this->meta($request, $s),
        ]);
    }

    /** Project a chat/question row for the client. */
    private function message(SessionMessage $m, Request $request): array
    {
        $pid = $this->participationId($request);
        $p = $m->participation;
        $mine = (int) $m->participation_id === $pid;

        return [
            'id' => $m->id,
            'body' => $m->body,
            'author' => $p?->contact?->fullName() ?: 'Attendee',
            'author_image' => $p?->profile_data['image_url'] ?? null,
            'author_id' => $p?->uuid,
            'is_mine' => $mine,
            'upvotes' => (int) $m->upvotes,
            'voted' => collect($m->meta['voters'] ?? [])->contains($pid),
            'is_answered' => (bool) $m->is_answered,
            'is_hidden' => (bool) $m->is_hidden,
            'is_pinned' => (bool) $m->is_pinned,
            'status' => $m->status,
            'can_delete' => $mine || $this->canModerate($request),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    /**
     * Project polls with tallies. Attendees don't get the per-option breakdown
     * while results are held back — the numbers simply aren't in the response,
     * rather than being hidden in the client where anyone could read them off
     * the network tab.
     */
    private function pollPayload($polls, int $pid, bool $host): \Illuminate\Support\Collection
    {
        $pollIds = $polls->pluck('id');

        $tallies = SessionPollVote::whereIn('session_poll_id', $pollIds)
            ->select('session_poll_id', 'option_id', DB::raw('count(*) as c'))
            ->groupBy('session_poll_id', 'option_id')
            ->get()
            ->groupBy('session_poll_id');

        $mine = SessionPollVote::whereIn('session_poll_id', $pollIds)
            ->where('participation_id', $pid)
            ->pluck('option_id', 'session_poll_id');

        return $polls->map(function (SessionPoll $p) use ($tallies, $mine, $host) {
            $counts = ($tallies[$p->id] ?? collect())->keyBy('option_id');
            $total = (int) $counts->sum('c');
            $reveal = $host || $p->resultsVisible();

            $options = collect($p->options)->map(fn ($o) => [
                'id' => $o['id'],
                'text' => $o['text'],
                'votes' => $reveal ? (int) ($counts[$o['id']]->c ?? 0) : 0,
            ])->values();

            return [
                'id' => $p->id,
                'question' => $p->question,
                'options' => $options,
                'total_votes' => $total,
                'status' => $p->status,
                'is_active' => $p->isLive(),
                'show_results' => (bool) $p->show_results,
                'results_visible' => $reveal,
                'my_vote' => $mine[$p->id] ?? null,
                'created_at' => $p->created_at?->toIso8601String(),
            ];
        });
    }

    /** Shared by the host (watch page) and organizer (admin) poll writers. */
    private function applyPollChanges(SessionPoll $p, array $data): void
    {
        if (! empty($data['question'])) {
            $p->question = trim($data['question']);
        }
        if (! empty($data['options'])) {
            $p->options = $this->normalizeOptions($data['options']);
        }
        if (array_key_exists('show_results', $data) && $data['show_results'] !== null) {
            $p->show_results = $data['show_results'];
        }
        if (! empty($data['status']) && $data['status'] !== $p->status) {
            $p->status = $data['status'];
            if ($data['status'] === SessionPoll::STATUS_LIVE) {
                $p->published_at ??= now();
                $p->closed_at = null;
            }
            if ($data['status'] === SessionPoll::STATUS_CLOSED) {
                $p->closed_at = now();
            }
        }
        $p->save();
    }

    /** Trim, drop blanks, and re-key as o1..oN so tallies stay addressable. */
    private function normalizeOptions(array $options): array
    {
        return collect($options)
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->values()
            ->map(fn ($t, $i) => ['id' => 'o'.($i + 1), 'text' => $t])
            ->all();
    }
}
