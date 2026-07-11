<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\SessionMessage;
use App\Models\SessionPoll;
use App\Models\SessionPollVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Organizer-side engagement management for a session (Showcase › Sessions ›
 * Engagement): author live polls and moderate the chat / Q&A that attendees
 * post from the watch page.
 *
 * The host moderates in-the-moment from the watch page
 * (SessionEngagementController); this is the organizer's equivalent — poll
 * set-up before the session, and clean-up during or after it. Sessions are
 * resolved by uuid so the tenant GUC is set before RLS-guarded reads (mirrors
 * SessionController).
 */
class SessionPollController extends Controller
{
    // ── Polls ───────────────────────────────────────────────────────────────
    public function index(Request $request, string $uuid): JsonResponse
    {
        $session = Session::where('uuid', $uuid)->firstOrFail();

        $polls = SessionPoll::where('session_id', $session->id)->orderByDesc('id')->get();

        return response()->json(['data' => $polls->map(fn ($p) => $this->withTally($p))]);
    }

    public function store(Request $request, string $uuid): JsonResponse
    {
        $session = Session::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'question' => ['required', 'string', 'max:300'],
            'options' => ['required', 'array', 'min:2', 'max:8'],
            'options.*' => ['required', 'string', 'max:200'],
            'status' => ['nullable', 'in:draft,live'],
            'show_results' => ['nullable', 'boolean'],
        ]);

        // Organizers usually write polls ahead of the session, so a poll drafted
        // here stays invisible to attendees until someone launches it.
        $status = $data['status'] ?? SessionPoll::STATUS_DRAFT;

        $poll = SessionPoll::create([
            'event_id' => $session->event_id,
            'session_id' => $session->id,
            'question' => trim($data['question']),
            'options' => $this->normalizeOptions($data['options']),
            'status' => $status,
            'show_results' => $data['show_results'] ?? true,
            'published_at' => $status === SessionPoll::STATUS_LIVE ? now() : null,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['data' => $this->withTally($poll)], 201);
    }

    public function update(Request $request, int $poll): JsonResponse
    {
        $model = SessionPoll::findOrFail($poll);

        $data = $request->validate([
            'status' => ['nullable', 'in:draft,live,closed'],
            'show_results' => ['nullable', 'boolean'],
            'question' => ['nullable', 'string', 'max:300'],
            'options' => ['nullable', 'array', 'min:2', 'max:8'],
            'options.*' => ['required', 'string', 'max:200'],
        ]);

        if (! empty($data['question'])) {
            $model->question = trim($data['question']);
        }
        if (! empty($data['options'])) {
            $model->options = $this->normalizeOptions($data['options']);
        }
        if (array_key_exists('show_results', $data) && $data['show_results'] !== null) {
            $model->show_results = $data['show_results'];
        }
        if (! empty($data['status']) && $data['status'] !== $model->status) {
            $model->status = $data['status'];
            if ($data['status'] === SessionPoll::STATUS_LIVE) {
                $model->published_at ??= now();
                $model->closed_at = null;
            }
            if ($data['status'] === SessionPoll::STATUS_CLOSED) {
                $model->closed_at = now();
            }
        }
        $model->save();

        return response()->json(['data' => $this->withTally($model)]);
    }

    public function destroy(int $poll): JsonResponse
    {
        SessionPoll::findOrFail($poll)->delete();

        return response()->json(['status' => 'success']);
    }

    // ── Chat / Q&A moderation ───────────────────────────────────────────────
    /**
     * Everything attendees have posted in this session, including the hidden and
     * pending rows — this is the moderation queue, so nothing is filtered out.
     */
    public function messages(Request $request, string $uuid): JsonResponse
    {
        $session = Session::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'kind' => ['nullable', 'in:chat,question'],
            'status' => ['nullable', 'in:published,pending,rejected'],
        ]);

        $rows = SessionMessage::where('session_id', $session->id)
            ->when($data['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->with('participation.contact')
            ->orderByDesc('is_pinned')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (SessionMessage $m) => [
                'id' => $m->id,
                'kind' => $m->kind,
                'body' => $m->body,
                'author' => $m->participation?->contact?->fullName() ?: 'Attendee',
                'author_image' => $m->participation?->profile_data['image_url'] ?? null,
                'upvotes' => (int) $m->upvotes,
                'status' => $m->status,
                'is_hidden' => (bool) $m->is_hidden,
                'is_pinned' => (bool) $m->is_pinned,
                'is_answered' => (bool) $m->is_answered,
                'created_at' => $m->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'pending' => $rows->where('status', SessionMessage::STATUS_PENDING)->count(),
                'hidden' => $rows->where('is_hidden', true)->count(),
                'qa_moderation' => $session->qaModerated(),
            ],
        ]);
    }

    public function messageUpdate(Request $request, int $message): JsonResponse
    {
        $m = SessionMessage::findOrFail($message);

        $data = $request->validate([
            'is_hidden' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_answered' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:published,pending,rejected'],
        ]);

        foreach (['is_hidden', 'is_pinned'] as $flag) {
            if (array_key_exists($flag, $data) && $data[$flag] !== null) {
                $m->{$flag} = $data[$flag];
            }
        }
        if (array_key_exists('is_answered', $data) && $data['is_answered'] !== null) {
            $m->is_answered = $data['is_answered'];
            $m->answered_at = $data['is_answered'] ? now() : null;
        }
        if (! empty($data['status'])) {
            $m->status = $data['status'];
        }
        $m->moderated_at = now();
        $m->save();

        return response()->json(['status' => 'success']);
    }

    public function messageDestroy(int $message): JsonResponse
    {
        SessionMessage::findOrFail($message)->delete(); // soft — recoverable

        return response()->json(['status' => 'success']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────
    /** Attach per-option vote tallies + total for the organizer view. */
    private function withTally(SessionPoll $p): array
    {
        $counts = SessionPollVote::where('session_poll_id', $p->id)
            ->select('option_id', DB::raw('count(*) as c'))
            ->groupBy('option_id')
            ->pluck('c', 'option_id');

        $options = collect($p->options)->map(fn ($o) => [
            'id' => $o['id'],
            'text' => $o['text'],
            'votes' => (int) ($counts[$o['id']] ?? 0),
        ])->values();

        return [
            'id' => $p->id,
            'question' => $p->question,
            'options' => $options,
            'total_votes' => (int) $counts->sum(),
            'status' => $p->status,
            'is_active' => $p->isLive(),
            'show_results' => (bool) $p->show_results,
            'created_at' => $p->created_at?->toIso8601String(),
        ];
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
