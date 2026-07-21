<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContestEntryResource;
use App\Http\Resources\ContestResource;
use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Contests (Event Engagement). Event-scoped on index/store; id-based on
 * update/destroy (resolved here so the tenant GUC is set and RLS doesn't hide
 * the row at bind time). Mirrors the BreakoutRoomController conventions.
 */
class ContestController extends Controller
{
    private const TYPES = ['entry', 'response'];
    private const WINNER_CHOOSERS = ['admin', 'most_likes'];

    public function index(string $uuid): AnonymousResourceCollection
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $contests = Contest::where('event_id', $event->id)->orderByDesc('id')->get();

        return ContestResource::collection($contests);
    }

    public function store(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate($this->rules(required: true));

        $contest = Contest::create($this->payload($request, $data) + [
            'event_id' => $event->id,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['data' => new ContestResource($contest)], 201);
    }

    public function show(int $contest): JsonResponse
    {
        return response()->json(['data' => new ContestResource(Contest::findOrFail($contest))]);
    }

    public function update(Request $request, int $contest): JsonResponse
    {
        $model = Contest::findOrFail($contest);

        $data = $request->validate($this->rules(required: false));

        foreach ($this->payload($request, $data, partial: true) as $key => $value) {
            $model->{$key} = $value;
        }
        $model->updated_by = $request->user()?->id;
        $model->save();

        return response()->json(['data' => new ContestResource($model)]);
    }

    public function destroy(int $contest): JsonResponse
    {
        Contest::findOrFail($contest)->delete();

        return response()->json(['status' => 'success']);
    }

    /**
     * Attendee entries in a contest, for the organizer's review drawer. Covers
     * both moderation (approve/reject when allow_moderate_entries is on) and
     * picking winners on an `admin` contest.
     */
    public function entries(Request $request, int $contest): JsonResponse
    {
        $model = Contest::findOrFail($contest);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'pending', 'approved', 'rejected'])],
            'sort' => ['nullable', Rule::in(['recent', 'top'])],
        ]);

        $query = ContestEntry::where('contest_id', $model->id)
            ->where('kind', 'entry')
            ->with('participation.contact');

        if (! empty($data['status']) && $data['status'] !== 'all') {
            $query->where('status', $data['status']);
        }

        ($data['sort'] ?? 'recent') === 'top'
            ? $query->orderByDesc('like_count')->orderByDesc('id')
            : $query->orderByDesc('id');

        $entries = $query->limit(300)->get();

        return response()->json([
            'data' => ContestEntryResource::collection($entries)->toArray($request),
            'meta' => [
                'counts' => ContestEntry::where('contest_id', $model->id)->where('kind', 'entry')
                    ->groupBy('status')->selectRaw('status, count(*) as c')->pluck('c', 'status'),
                'winner_number' => (int) $model->winner_number,
                'winner_chooser' => $model->winner_chooser,
            ],
        ]);
    }

    /** Approve/reject an entry, or flag it as a winner. */
    public function updateEntry(Request $request, int $contest, string $entry): JsonResponse
    {
        $model = Contest::findOrFail($contest);
        $row = ContestEntry::where('contest_id', $model->id)->where('uuid', $entry)->firstOrFail();

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected'])],
            'is_winner' => ['sometimes', 'boolean'],
            'rank' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        if (array_key_exists('status', $data)) {
            $row->status = $data['status'];
        }

        if (array_key_exists('is_winner', $data)) {
            $row->is_winner = (bool) $data['is_winner'];
            // A winner is by definition an accepted entry.
            if ($row->is_winner) {
                $row->status = 'approved';
            }
            $row->rank = $row->is_winner ? ($data['rank'] ?? $row->rank) : null;
        }

        $row->save();

        return response()->json(['data' => (new ContestEntryResource($row))->toArray($request)]);
    }

    public function destroyEntry(int $contest, string $entry): JsonResponse
    {
        $model = Contest::findOrFail($contest);
        ContestEntry::where('contest_id', $model->id)->where('uuid', $entry)->firstOrFail()->delete();

        return response()->json(['status' => 'success']);
    }

    /** Map validated input to column values; `partial` only touches provided keys. */
    private function payload(Request $request, array $data, bool $partial = false): array
    {
        $columns = [
            'title', 'contest_type', 'description', 'description_file_url', 'description_file_name',
            'starts_at', 'ends_at', 'banner_url', 'caption',
            'character_limit', 'points_for_entry', 'points_for_response',
            'allow_photos', 'allow_videos', 'allow_selfie',
            'winner_chooser', 'winner_number', 'winning_points', 'equal_points_distribution',
            'attach_mandatory', 'allow_multiple_entries', 'allow_moderate_entries',
            'attendees_can_see_others_entries', 'attendees_can_see_other_comments',
            'meta',
        ];

        $out = [];
        foreach ($columns as $col) {
            // Omit columns the request never sent so the DB's own DEFAULT
            // applies instead of an explicit NULL against a NOT NULL column.
            if (! $request->has($col)) {
                continue;
            }
            $out[$col] = $data[$col] ?? $request->input($col);
        }

        return $out;
    }

    private function rules(bool $required): array
    {
        $req = $required ? 'required' : 'sometimes';

        return [
            'title' => [$req, 'string', 'max:200'],
            'contest_type' => ['sometimes', Rule::in(self::TYPES)],
            'description' => ['nullable', 'string', 'max:5000'],
            'description_file_url' => ['nullable', 'string', 'max:2000'],
            'description_file_name' => ['nullable', 'string', 'max:255'],
            'starts_at' => [$req, 'date'],
            'ends_at' => [$req, 'date', 'after:starts_at'],
            'banner_url' => ['nullable', 'string', 'max:2000'],
            'caption' => ['nullable', 'string', 'max:500'],
            'character_limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'points_for_entry' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'points_for_response' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'allow_photos' => ['nullable', 'boolean'],
            'allow_videos' => ['nullable', 'boolean'],
            'allow_selfie' => ['nullable', 'boolean'],
            'winner_chooser' => ['sometimes', Rule::in(self::WINNER_CHOOSERS)],
            'winner_number' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'winning_points' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'equal_points_distribution' => ['nullable', 'boolean'],
            'attach_mandatory' => ['nullable', 'boolean'],
            'allow_multiple_entries' => ['nullable', 'boolean'],
            'allow_moderate_entries' => ['nullable', 'boolean'],
            'attendees_can_see_others_entries' => ['nullable', 'boolean'],
            'attendees_can_see_other_comments' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
