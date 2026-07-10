<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\SessionPoll;
use App\Models\SessionPollVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Organizer-side live poll authoring for a session (Showcase › Sessions ›
 * Stream). Attendees vote via SessionEngagementController; the organizer sees
 * the live tally here. Session resolved by uuid so the tenant GUC is set before
 * RLS-guarded reads (mirrors SessionController).
 */
class SessionPollController extends Controller
{
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
        ]);

        $options = collect($data['options'])
            ->map(fn ($t) => trim($t))
            ->filter()
            ->values()
            ->map(fn ($t, $i) => ['id' => 'o'.($i + 1), 'text' => $t])
            ->all();

        $poll = SessionPoll::create([
            'event_id' => $session->event_id,
            'session_id' => $session->id,
            'question' => trim($data['question']),
            'options' => $options,
            'is_active' => true,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['data' => $this->withTally($poll)], 201);
    }

    public function update(Request $request, int $poll): JsonResponse
    {
        $model = SessionPoll::findOrFail($poll);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $model->is_active = $data['is_active'];
        $model->save();

        return response()->json(['data' => $this->withTally($model)]);
    }

    public function destroy(int $poll): JsonResponse
    {
        SessionPoll::findOrFail($poll)->delete();

        return response()->json(['status' => 'success']);
    }

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
            'is_active' => $p->is_active,
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }
}
