<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpoLensPhotoResource;
use App\Jobs\ExpoLens\ProcessExpoLensPhotoJob;
use App\Jobs\ExpoLens\RematchEventFacesJob;
use App\Jobs\ExpoLens\ReprocessEventFacesJob;
use App\Models\Event;
use App\Models\ExpoLensPhoto;
use App\Models\File;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ExpoLensAdminController extends Controller
{
    public function index(string $uuid, Request $request): AnonymousResourceCollection
    {
        $event = $this->event($uuid);
        $query = ExpoLensPhoto::where('event_id', $event->id)
            ->withCount('matches')
            ->latest();

        if ($request->filled('processing_status')) {
            $query->where('processing_status', $request->string('processing_status'));
        }
        if ($request->filled('moderation_status')) {
            $query->where('moderation_status', $request->string('moderation_status'));
        }
        if ($request->filled('search')) {
            $search = '%'.addcslashes($request->string('search')->toString(), '%_').'%';
            $query->where(fn ($q) => $q
                ->where('caption', 'ilike', $search)
                ->orWhere('album', 'ilike', $search));
        }

        return ExpoLensPhotoResource::collection(
            $query->limit(min($request->integer('per_page', 100), 200))->get()
        );
    }

    public function store(string $uuid, Request $request): AnonymousResourceCollection
    {
        $event = $this->event($uuid);
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:100'],
            'images.*.file_id' => ['required', 'integer'],
            'images.*.caption' => ['nullable', 'string', 'max:255'],
            'images.*.album' => ['nullable', 'string', 'max:120'],
            'photographer_name' => ['nullable', 'string', 'max:150'],
        ]);

        $fileIds = collect($data['images'])->pluck('file_id')->unique()->values();
        $files = File::where('organization_id', $event->organization_id)
            ->where('collection', 'expolens')
            ->whereIn('id', $fileIds)
            ->get()
            ->keyBy('id');

        abort_unless($files->count() === $fileIds->count(), 422, 'One or more ExpoLens uploads are invalid.');

        $created = collect($data['images'])->map(function (array $image) use ($event, $request, $files, $data) {
            $file = $files->get($image['file_id']);
            $photo = ExpoLensPhoto::create([
                'event_id' => $event->id,
                'file_id' => $file->id,
                'url' => Storage::disk($file->disk)->url($file->path),
                'caption' => $image['caption'] ?? null,
                'album' => $image['album'] ?? null,
                'processing_status' => 'pending',
                'moderation_status' => 'pending',
                'uploaded_by' => $request->user()->id,
                'photographer_meta' => array_filter([
                    'name' => $data['photographer_name'] ?? null,
                    'filename' => $file->filename,
                ]),
            ]);

            ProcessExpoLensPhotoJob::dispatch($event->organization_id, $photo->id);

            return $photo;
        });

        return ExpoLensPhotoResource::collection($created);
    }

    public function update(string $uuid, string $photo, Request $request): JsonResponse
    {
        $event = $this->event($uuid);
        $model = $this->photo($event, $photo);
        $data = $request->validate([
            'caption' => ['sometimes', 'nullable', 'string', 'max:255'],
            'album' => ['sometimes', 'nullable', 'string', 'max:120'],
            'moderation_status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        $model->update($data);

        return response()->json(['data' => new ExpoLensPhotoResource($model->fresh()->loadCount('matches'))]);
    }

    public function destroy(string $uuid, string $photo): JsonResponse
    {
        $event = $this->event($uuid);
        $model = $this->photo($event, $photo);
        $model->matches()->delete();
        $model->faces()->delete();
        $model->delete();

        return response()->json(['message' => 'ExpoLens photo removed.']);
    }

    public function matches(string $uuid, string $photo): JsonResponse
    {
        $event = $this->event($uuid);
        $model = $this->photo($event, $photo);
        $matches = $model->matches()
            ->with(['participation.contact', 'face'])
            ->orderByDesc('similarity_score')
            ->get()
            ->map(fn ($match) => $this->matchData($match));

        return response()->json(['data' => $matches]);
    }

    public function attendees(string $uuid, Request $request): JsonResponse
    {
        $event = $this->event($uuid);
        $query = Participation::with('contact')
            ->withCount('expoLensMatches')
            ->where('event_id', $event->id)
            ->where('role', 'attendee');

        if ($request->filled('search')) {
            $search = '%'.addcslashes($request->string('search')->toString(), '%_').'%';
            $query->whereHas('contact', fn ($q) => $q
                ->where('first_name', 'ilike', $search)
                ->orWhere('last_name', 'ilike', $search)
                ->orWhere('email', 'ilike', $search));
        }

        $data = $query->orderByDesc('expo_lens_matches_count')
            ->limit(50)
            ->get()
            ->map(fn (Participation $participation) => [
                'id' => $participation->uuid,
                'name' => $participation->contact?->fullName(),
                'email' => $participation->contact?->email,
                'avatar_url' => data_get($participation->profile_data, 'avatar_url'),
                'matches_count' => $participation->expo_lens_matches_count,
            ]);

        return response()->json(['data' => $data]);
    }

    public function attendeeMatches(string $uuid, string $participation): AnonymousResourceCollection
    {
        $event = $this->event($uuid);
        $attendee = Participation::where('event_id', $event->id)
            ->where('uuid', $participation)
            ->firstOrFail();

        $photos = ExpoLensPhoto::where('event_id', $event->id)
            ->whereHas('matches', fn ($q) => $q->where('participation_id', $attendee->id))
            ->withCount('matches')
            ->latest()
            ->paginate(40);

        return ExpoLensPhotoResource::collection($photos);
    }

    /**
     * Bulk-set moderation status. Approving is the gate that puts a photo in
     * front of attendees, and organizers do it a hundred shots at a time.
     */
    public function bulkModerate(string $uuid, Request $request): JsonResponse
    {
        $event = $this->event($uuid);
        $data = $request->validate([
            'moderation_status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'ids' => ['nullable', 'array', 'max:500'],
            'ids.*' => ['string'],
            'all_pending' => ['nullable', 'boolean'],
        ]);

        $query = ExpoLensPhoto::where('event_id', $event->id);

        if ($data['all_pending'] ?? false) {
            $query->where('moderation_status', 'pending');
        } else {
            abort_if(empty($data['ids']), 422, 'Select at least one photo.');
            $query->whereIn('uuid', $data['ids']);
        }

        $updated = $query->update(['moderation_status' => $data['moderation_status']]);

        return response()->json([
            'message' => "{$updated} photo(s) marked {$data['moderation_status']}.",
            'updated' => $updated,
        ]);
    }

    /** Re-run face detection for a single photo — the retry for a failed upload. */
    public function retry(string $uuid, string $photo): JsonResponse
    {
        $event = $this->event($uuid);
        $model = $this->photo($event, $photo);

        $model->update(['processing_status' => 'pending', 'failure_reason' => null]);
        ProcessExpoLensPhotoJob::dispatch($event->organization_id, $model->id);

        return response()->json(['message' => 'Photo queued for reprocessing.'], 202);
    }

    /**
     * Confirm or drop a suggested match. Confirmed matches survive a rematch;
     * rejecting removes the row so the attendee stops seeing that photo.
     */
    public function updateMatch(string $uuid, string $photo, string $participation, Request $request): JsonResponse
    {
        $event = $this->event($uuid);
        $model = $this->photo($event, $photo);
        $data = $request->validate(['confirmed' => ['required', 'boolean']]);

        $attendee = Participation::where('event_id', $event->id)
            ->where('uuid', $participation)
            ->firstOrFail();

        $match = $model->matches()->where('participation_id', $attendee->id)->firstOrFail();

        if ($data['confirmed']) {
            $match->update(['confirmed' => true]);

            return response()->json(['message' => 'Match confirmed.']);
        }

        $match->delete();

        return response()->json(['message' => 'Match removed.']);
    }

    /**
     * Queue event-wide matching.
     *
     * mode=rematch (default) reuses the face vectors already stored on each
     * photo — no face-service calls. mode=full re-detects every photo and is
     * only needed when the detection model or quality floor changed.
     */
    public function reprocess(string $uuid, Request $request): JsonResponse
    {
        $event = $this->event($uuid);
        $mode = $request->string('mode', 'rematch')->toString();

        if ($mode === 'full') {
            ReprocessEventFacesJob::dispatch($event->organization_id, $event->id);

            return response()->json(['message' => 'ExpoLens photos were queued for full reprocessing.'], 202);
        }

        RematchEventFacesJob::dispatch($event->organization_id, $event->id);

        return response()->json(['message' => 'ExpoLens photos were queued for rematching.'], 202);
    }

    private function event(string $uuid): Event
    {
        return Event::where('uuid', $uuid)->firstOrFail();
    }

    private function photo(Event $event, string $uuid): ExpoLensPhoto
    {
        return ExpoLensPhoto::where('event_id', $event->id)->where('uuid', $uuid)->firstOrFail();
    }

    private function matchData($match): array
    {
        $participation = $match->participation;

        return [
            'participation_id' => $participation->uuid,
            'name' => $participation->contact?->fullName(),
            'email' => $participation->contact?->email,
            'avatar_url' => data_get($participation->profile_data, 'avatar_url'),
            'score' => $match->similarity_score,
            'confirmed' => $match->confirmed,
            'bbox' => $match->face?->bbox,
        ];
    }
}
