<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContestResource;
use App\Models\Contest;
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
            'contest_type' => ['nullable', Rule::in(self::TYPES)],
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
            'winner_chooser' => ['nullable', Rule::in(self::WINNER_CHOOSERS)],
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
