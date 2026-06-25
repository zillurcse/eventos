<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryImageResource;
use App\Models\Event;
use App\Models\GalleryImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Per-event image gallery (Content Hub → Image Gallery). Organizer-side CRUD,
 * bulk add and album-aware ordering.
 */
class GalleryImageController extends Controller
{
    public function index(string $uuid, Request $request): AnonymousResourceCollection
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $query = GalleryImage::where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($request->filled('album')) {
            $query->where('album', $request->string('album'));
        }

        return GalleryImageResource::collection($query->get());
    }

    /** Bulk add: accepts one or more already-uploaded images. */
    public function store(string $uuid, Request $request): AnonymousResourceCollection
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*.file_id' => ['nullable', 'integer', 'exists:files,id'],
            'images.*.url' => ['required', 'string', 'max:1000'],
            'images.*.caption' => ['nullable', 'string', 'max:255'],
            'images.*.album' => ['nullable', 'string', 'max:120'],
        ]);

        // Append after the current max sort_order for the event.
        $start = (int) GalleryImage::where('event_id', $event->id)->max('sort_order');

        $created = collect($data['images'])->map(function (array $img, int $i) use ($event, $start, $request) {
            return GalleryImage::create([
                'event_id' => $event->id,
                'file_id' => $img['file_id'] ?? null,
                'url' => $img['url'],
                'caption' => $img['caption'] ?? null,
                'album' => $img['album'] ?? null,
                'sort_order' => $start + $i + 1,
                'created_by' => $request->user()->id,
            ]);
        });

        return GalleryImageResource::collection($created);
    }

    public function update(string $uuid, string $image, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $model = GalleryImage::where('event_id', $event->id)->where('uuid', $image)->firstOrFail();

        $data = $request->validate([
            'caption' => ['sometimes', 'nullable', 'string', 'max:255'],
            'album' => ['sometimes', 'nullable', 'string', 'max:120'],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        $model->update($data);

        return response()->json(['data' => new GalleryImageResource($model->fresh())]);
    }

    public function destroy(string $uuid, string $image): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        GalleryImage::where('event_id', $event->id)->where('uuid', $image)->firstOrFail()->delete();

        return response()->json(['message' => 'Image removed.']);
    }

    /** Persist a new ordering — accepts the full ordered list of image uuids. */
    public function reorder(string $uuid, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['string'],
        ]);

        DB::transaction(function () use ($event, $data) {
            foreach ($data['order'] as $i => $imageUuid) {
                GalleryImage::where('event_id', $event->id)
                    ->where('uuid', $imageUuid)
                    ->update(['sort_order' => $i + 1]);
            }
        });

        return response()->json(['message' => 'Order saved.']);
    }
}
