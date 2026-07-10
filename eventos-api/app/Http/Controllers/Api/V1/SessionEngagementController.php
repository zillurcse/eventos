<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Participation;
use App\Models\Session;
use App\Models\SessionMessage;
use App\Models\SessionPoll;
use App\Models\SessionPollVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Attendee-facing live-session engagement panel (watch page): group Chat, Q&A,
 * live Polls and the Attendees list. Runs under the `participant` middleware, so
 * event_id / participation_id are on the request and the org GUC is set. Realtime
 * is client polling (no websocket fan-out) — cheap and simple at any scale.
 */
class SessionEngagementController extends Controller
{
    // ── Chat ────────────────────────────────────────────────────────────────
    public function chatIndex(Request $request): JsonResponse
    {
        $s = $this->session($request);

        $rows = SessionMessage::where('session_id', $s->id)
            ->where('kind', 'chat')
            ->with('participation.contact')
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->reverse()
            ->values();

        return response()->json(['data' => $rows->map(fn ($m) => $this->message($m, $request))]);
    }

    public function chatSend(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $m = SessionMessage::create([
            'event_id' => $request->attributes->get('event_id'),
            'session_id' => $s->id,
            'participation_id' => $request->attributes->get('participation_id'),
            'kind' => 'chat',
            'body' => trim($data['body']),
        ])->load('participation.contact');

        return response()->json(['data' => $this->message($m, $request)], 201);
    }

    // ── Q&A ─────────────────────────────────────────────────────────────────
    public function questionIndex(Request $request): JsonResponse
    {
        $s = $this->session($request);

        $rows = SessionMessage::where('session_id', $s->id)
            ->where('kind', 'question')
            ->with('participation.contact')
            ->orderByDesc('upvotes')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows->map(fn ($m) => $this->message($m, $request))]);
    }

    public function questionAsk(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);

        $m = SessionMessage::create([
            'event_id' => $request->attributes->get('event_id'),
            'session_id' => $s->id,
            'participation_id' => $request->attributes->get('participation_id'),
            'kind' => 'question',
            'body' => trim($data['body']),
            'meta' => ['voters' => []],
        ])->load('participation.contact');

        return response()->json(['data' => $this->message($m, $request)], 201);
    }

    public function questionUpvote(Request $request): JsonResponse
    {
        $this->session($request);
        $pid = (int) $request->attributes->get('participation_id');

        $m = SessionMessage::where('kind', 'question')->findOrFail((int) $request->route('message'));
        $voters = collect($m->meta['voters'] ?? []);

        if ($voters->contains($pid)) {
            $voters = $voters->reject(fn ($v) => (int) $v === $pid)->values();
        } else {
            $voters->push($pid);
        }
        $m->meta = array_merge($m->meta ?? [], ['voters' => $voters->all()]);
        $m->upvotes = $voters->count();
        $m->save();

        return response()->json(['data' => ['upvotes' => $m->upvotes, 'voted' => $voters->contains($pid)]]);
    }

    // ── Polls ───────────────────────────────────────────────────────────────
    public function pollIndex(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $pid = (int) $request->attributes->get('participation_id');

        $polls = SessionPoll::where('session_id', $s->id)->orderByDesc('id')->get();
        $pollIds = $polls->pluck('id');

        // Tally all votes for these polls in one grouped query.
        $tallies = SessionPollVote::whereIn('session_poll_id', $pollIds)
            ->select('session_poll_id', 'option_id', DB::raw('count(*) as c'))
            ->groupBy('session_poll_id', 'option_id')
            ->get()
            ->groupBy('session_poll_id');

        $mine = SessionPollVote::whereIn('session_poll_id', $pollIds)
            ->where('participation_id', $pid)
            ->pluck('option_id', 'session_poll_id');

        return response()->json(['data' => $polls->map(function (SessionPoll $p) use ($tallies, $mine) {
            $counts = ($tallies[$p->id] ?? collect())->keyBy('option_id');
            $total = $counts->sum('c');
            $options = collect($p->options)->map(fn ($o) => [
                'id' => $o['id'],
                'text' => $o['text'],
                'votes' => (int) ($counts[$o['id']]->c ?? 0),
            ])->values();

            return [
                'id' => $p->id,
                'question' => $p->question,
                'options' => $options,
                'total_votes' => (int) $total,
                'is_active' => $p->is_active,
                'my_vote' => $mine[$p->id] ?? null,
            ];
        })]);
    }

    public function pollVote(Request $request): JsonResponse
    {
        $this->session($request);
        $pid = (int) $request->attributes->get('participation_id');

        $p = SessionPoll::findOrFail((int) $request->route("poll"));
        if (! $p->is_active) {
            return response()->json(['message' => 'This poll is closed.'], 422);
        }

        $data = $request->validate(['option_id' => ['required', 'string', 'max:20']]);
        $valid = collect($p->options)->pluck('id')->contains($data['option_id']);
        if (! $valid) {
            return response()->json(['message' => 'Invalid option.'], 422);
        }

        SessionPollVote::updateOrCreate(
            ['session_poll_id' => $p->id, 'participation_id' => $pid],
            ['option_id' => $data['option_id']],
        );

        return $this->pollIndex($request);
    }

    // ── Attendees ─────────────────────────────────────────────────────────────
    public function attendees(Request $request): JsonResponse
    {
        $s = $this->session($request);
        $eventId = (int) $request->attributes->get('event_id');

        $speakerIds = $s->speakers()->pluck('participations.id')->all();

        $people = Participation::where('event_id', $eventId)
            ->whereIn('role', ['attendee', 'speaker'])
            ->with('contact')
            ->limit(200)
            ->get();

        // Online = a fresh presence key in Redis.
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
            'online' => isset($online[$p->id]),
        ])->sortByDesc(fn ($x) => [$x['online'], $x['is_speaker']])->values();

        return response()->json([
            'data' => $list,
            'meta' => ['online' => count($online), 'total' => $people->count()],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    /** Resolve a session by uuid within the current event; 404 otherwise. */
    private function session(Request $request): Session
    {
        // Read the session uuid from the route by name: with `events/{event}`
        // as the group prefix, positional controller args would receive the
        // event uuid, so we look it up explicitly (matches the codebase pattern).
        return Session::where('event_id', $request->attributes->get('event_id'))
            ->where('uuid', (string) $request->route('sessionUuid'))
            ->firstOrFail();
    }

    /** Project a chat/question row for the client. */
    private function message(SessionMessage $m, Request $request): array
    {
        $pid = (int) $request->attributes->get('participation_id');
        $p = $m->participation;

        return [
            'id' => $m->id,
            'body' => $m->body,
            'author' => $p?->contact?->fullName() ?: 'Attendee',
            'author_image' => $p?->profile_data['image_url'] ?? null,
            'is_mine' => (int) $m->participation_id === $pid,
            'upvotes' => (int) $m->upvotes,
            'voted' => collect($m->meta['voters'] ?? [])->contains($pid),
            'is_answered' => (bool) $m->is_answered,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
