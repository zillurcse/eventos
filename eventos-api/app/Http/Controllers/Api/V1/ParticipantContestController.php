<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContestEntryResource;
use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\ContestEntryLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The attendee side of Contests (the event site's "Contests" tab). The
 * organizer configures a contest in Engagement › Contests
 * (ContestController); here attendees browse them, submit entries, comment and
 * like, and — once a contest ends — see the winners.
 *
 * Runs behind the `participant` middleware, so the event, org GUC and the
 * caller's participation are already resolved on the request.
 */
class ParticipantContestController extends Controller
{
    /** Contests for this event, each with the viewer's own participation state. */
    public function index(Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $pid = (int) $request->attributes->get('participation_id');

        $contests = Contest::where('event_id', $eventId)->orderByDesc('starts_at')->get();

        // Two grouped counts rather than a per-contest query.
        $totals = ContestEntry::where('event_id', $eventId)
            ->where('kind', 'entry')->where('status', 'approved')
            ->groupBy('contest_id')
            ->selectRaw('contest_id, count(*) as c')->pluck('c', 'contest_id');

        $mine = ContestEntry::where('event_id', $eventId)
            ->where('kind', 'entry')->where('participation_id', $pid)
            ->groupBy('contest_id')
            ->selectRaw('contest_id, count(*) as c')->pluck('c', 'contest_id');

        return response()->json([
            'data' => $contests->map(fn (Contest $c) => $this->contestPayload(
                $c,
                (int) ($totals[$c->id] ?? 0),
                (int) ($mine[$c->id] ?? 0),
            ))->values(),
        ]);
    }

    /** One contest, with the winners once it has ended. */
    public function show(Request $request, string $event, string $contest): JsonResponse
    {
        $model = $this->contest($request, $contest);
        $pid = (int) $request->attributes->get('participation_id');

        $payload = $this->contestPayload(
            $model,
            (int) ContestEntry::where('contest_id', $model->id)
                ->where('kind', 'entry')->where('status', 'approved')->count(),
            (int) ContestEntry::where('contest_id', $model->id)
                ->where('kind', 'entry')->where('participation_id', $pid)->count(),
        );

        $payload['winners'] = ContestEntryResource::collection(
            $this->winners($model, $pid)
        )->toArray($request);

        return response()->json(['data' => $payload]);
    }

    /**
     * Entries in a contest. When the organizer keeps entries private
     * (attendees_can_see_others_entries off) the attendee only ever sees their
     * own — plus the winners, which are announced regardless.
     */
    public function entries(Request $request, string $event, string $contest): JsonResponse
    {
        $model = $this->contest($request, $contest);
        $pid = (int) $request->attributes->get('participation_id');

        $data = $request->validate([
            'sort' => ['nullable', 'in:recent,top'],
            'mine' => ['nullable', 'boolean'],
        ]);

        $query = ContestEntry::where('contest_id', $model->id)
            ->where('kind', 'entry')
            ->with(['participation.contact', 'likes' => fn ($q) => $q->where('participation_id', $pid)]);

        if ($request->boolean('mine')) {
            // "My entries" shows the author every state, including pending.
            $query->where('participation_id', $pid);
        } elseif ($model->attendees_can_see_others_entries) {
            // Everyone's approved entries, plus my own while they await review.
            $query->where(fn ($w) => $w->where('status', 'approved')->orWhere('participation_id', $pid));
        } else {
            $query->where(fn ($w) => $w->where('participation_id', $pid)->orWhere('is_winner', true));
        }

        ($data['sort'] ?? 'recent') === 'top'
            ? $query->orderByDesc('like_count')->orderByDesc('id')
            : $query->orderByDesc('id');

        return response()->json([
            'data' => ContestEntryResource::collection($query->limit(200)->get())->toArray($request),
        ]);
    }

    /** Submit an entry (or, in a response contest, a reply to the organizer's post). */
    public function store(Request $request, string $event, string $contest): JsonResponse
    {
        $model = $this->contest($request, $contest);
        $pid = (int) $request->attributes->get('participation_id');

        if ($model->phase() !== 'ongoing') {
            throw ValidationException::withMessages([
                'body' => $model->phase() === 'upcoming'
                    ? 'This contest hasn’t started yet.'
                    : 'This contest has ended.',
            ]);
        }

        $already = ContestEntry::where('contest_id', $model->id)
            ->where('kind', 'entry')->where('participation_id', $pid)->count();

        if ($already > 0 && ! $model->allow_multiple_entries) {
            throw ValidationException::withMessages(['body' => 'You have already entered this contest.']);
        }

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:'.max(1, (int) $model->character_limit)],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*.kind' => ['required_with:attachments', 'in:image,video'],
            'attachments.*.url' => ['required_with:attachments', 'url', 'max:2000'],
            'attachments.*.name' => ['nullable', 'string', 'max:255'],
        ]);

        $attachments = $this->allowedAttachments($model, $data['attachments'] ?? []);

        if ($model->contest_type === 'entry' && $model->attach_mandatory && empty($attachments)) {
            throw ValidationException::withMessages(['attachments' => 'This contest requires an attachment.']);
        }

        if (trim((string) ($data['body'] ?? '')) === '' && empty($attachments)) {
            throw ValidationException::withMessages(['body' => 'Write something or attach media.']);
        }

        $entry = ContestEntry::create([
            'event_id' => $request->attributes->get('event_id'),
            'contest_id' => $model->id,
            'participation_id' => $pid,
            'kind' => 'entry',
            'body' => $data['body'] ?? null,
            'attachments' => $attachments,
            'status' => $model->allow_moderate_entries ? 'pending' : 'approved',
            'awarded_points' => $model->contest_type === 'entry'
                ? (int) $model->points_for_entry
                : (int) $model->points_for_response,
        ]);

        return response()->json(['data' => (new ContestEntryResource($entry))->toArray($request)], 201);
    }

    /** Withdraw one of my own entries, while the contest is still running. */
    public function destroy(Request $request, string $event, string $entry): JsonResponse
    {
        $model = $this->entry($request, $entry);

        abort_unless(
            (int) $model->participation_id === (int) $request->attributes->get('participation_id'),
            403,
            'You can only remove your own entries.',
        );

        if ($model->parent_id) {
            ContestEntry::where('id', $model->parent_id)->where('comment_count', '>', 0)->decrement('comment_count');
        }

        $model->delete();

        return response()->json(['status' => 'success']);
    }

    /** Toggle my like on an entry. Returns the fresh count so the UI can settle. */
    public function like(Request $request, string $event, string $entry): JsonResponse
    {
        $model = $this->entry($request, $entry);
        $pid = (int) $request->attributes->get('participation_id');

        abort_if((int) $model->participation_id === $pid, 422, 'You cannot like your own entry.');

        $contest = Contest::findOrFail($model->contest_id);
        abort_if($contest->phase() === 'ended', 422, 'This contest has ended.');

        $existing = ContestEntryLike::where('contest_entry_id', $model->id)
            ->where('participation_id', $pid)->first();

        DB::transaction(function () use ($existing, $model, $pid) {
            if ($existing) {
                $existing->delete();
                ContestEntry::where('id', $model->id)->where('like_count', '>', 0)->decrement('like_count');
            } else {
                ContestEntryLike::create(['contest_entry_id' => $model->id, 'participation_id' => $pid]);
                ContestEntry::where('id', $model->id)->increment('like_count');
            }
        });

        return response()->json([
            'data' => [
                'liked' => ! $existing,
                'like_count' => (int) ContestEntry::whereKey($model->id)->value('like_count'),
            ],
        ]);
    }

    /** Comments on one entry (entry contests only). */
    public function comments(Request $request, string $event, string $entry): JsonResponse
    {
        $model = $this->entry($request, $entry);
        $contest = Contest::findOrFail($model->contest_id);
        $pid = (int) $request->attributes->get('participation_id');

        $query = ContestEntry::where('parent_id', $model->id)
            ->where('kind', 'comment')
            ->with('participation.contact');

        // With the switch off a viewer only sees the conversation they're part
        // of: their own comments, and every comment on their own entry.
        if (! $contest->attendees_can_see_other_comments && (int) $model->participation_id !== $pid) {
            $query->where('participation_id', $pid);
        }

        return response()->json([
            'data' => ContestEntryResource::collection($query->orderBy('id')->limit(200)->get())->toArray($request),
        ]);
    }

    public function comment(Request $request, string $event, string $entry): JsonResponse
    {
        $model = $this->entry($request, $entry);
        $contest = Contest::findOrFail($model->contest_id);

        abort_if($contest->phase() !== 'ongoing', 422, 'This contest is not accepting comments.');

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.max(1, (int) $contest->character_limit)],
        ]);

        $comment = ContestEntry::create([
            'event_id' => $request->attributes->get('event_id'),
            'contest_id' => $contest->id,
            'participation_id' => $request->attributes->get('participation_id'),
            'parent_id' => $model->id,
            'kind' => 'comment',
            'body' => $data['body'],
            'status' => 'approved',
        ]);

        ContestEntry::where('id', $model->id)->increment('comment_count');

        return response()->json(['data' => (new ContestEntryResource($comment))->toArray($request)], 201);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** Resolve a contest by uuid, constrained to the request's event. */
    private function contest(Request $request, string $uuid): Contest
    {
        return Contest::where('event_id', $request->attributes->get('event_id'))
            ->where('uuid', $uuid)->firstOrFail();
    }

    private function entry(Request $request, string $uuid): ContestEntry
    {
        return ContestEntry::where('event_id', $request->attributes->get('event_id'))
            ->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * The contest as the attendee sees it: the organizer's post plus what this
     * viewer may do with it right now.
     */
    private function contestPayload(Contest $contest, int $entryCount, int $myEntries): array
    {
        $phase = $contest->phase();

        return [
            'id' => $contest->uuid,
            'title' => $contest->title,
            'contest_type' => $contest->contest_type,
            'phase' => $phase,
            'description' => $contest->description,
            'description_file_url' => $contest->description_file_url,
            'description_file_name' => $contest->description_file_name,
            'starts_at' => $contest->starts_at?->toIso8601String(),
            'ends_at' => $contest->ends_at?->toIso8601String(),
            'banner_url' => $contest->banner_url,
            'caption' => $contest->caption,
            'character_limit' => (int) $contest->character_limit,
            'points' => (int) ($contest->contest_type === 'entry'
                ? $contest->points_for_entry
                : $contest->points_for_response),
            'allow_photos' => (bool) $contest->allow_photos,
            'allow_videos' => (bool) $contest->allow_videos,
            'allow_selfie' => (bool) $contest->allow_selfie,
            'attach_mandatory' => (bool) $contest->attach_mandatory,
            'allow_multiple_entries' => (bool) $contest->allow_multiple_entries,
            'moderated' => (bool) $contest->allow_moderate_entries,
            'can_see_others_entries' => (bool) $contest->attendees_can_see_others_entries,
            'can_see_other_comments' => (bool) $contest->attendees_can_see_other_comments,
            'winner_chooser' => $contest->winner_chooser,
            'winner_number' => (int) $contest->winner_number,
            'winning_points' => (int) $contest->winning_points,
            'entry_count' => $entryCount,
            'my_entry_count' => $myEntries,
            'can_enter' => $phase === 'ongoing' && ($contest->allow_multiple_entries || $myEntries === 0),
        ];
    }

    /**
     * Winners of an ended contest. `admin` contests use the flag the organizer
     * set; `most_likes` contests resolve themselves from the like counts, so
     * they need no organizer action at all.
     */
    private function winners(Contest $contest, int $pid)
    {
        if ($contest->phase() !== 'ended') {
            return collect();
        }

        $query = ContestEntry::where('contest_id', $contest->id)
            ->where('kind', 'entry')->where('status', 'approved')
            ->with(['participation.contact', 'likes' => fn ($q) => $q->where('participation_id', $pid)]);

        if ($contest->winner_chooser === 'most_likes') {
            return $query->where('like_count', '>', 0)
                ->orderByDesc('like_count')->orderBy('id')
                ->limit(max(1, (int) $contest->winner_number))->get();
        }

        return $query->where('is_winner', true)->orderBy('rank')->orderByDesc('like_count')->get();
    }

    /** Drop attachment kinds the organizer didn't enable for this contest. */
    private function allowedAttachments(Contest $contest, array $attachments): array
    {
        $allowed = array_filter([
            // Selfies arrive as ordinary images; the switch only changes the
            // prompt the attendee sees, not the payload.
            ($contest->allow_photos || $contest->allow_selfie) ? 'image' : null,
            $contest->allow_videos ? 'video' : null,
        ]);

        return array_values(array_filter(
            $attachments,
            fn ($a) => in_array($a['kind'] ?? '', $allowed, true),
        ));
    }
}
