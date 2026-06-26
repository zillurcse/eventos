<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CtaResource;
use App\Models\Cta;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Per-event sponsor CTAs (Communication → CTA). Organizer-side CRUD for the
 * three CTA flavours: image, video and text.
 */
class CtaController extends Controller
{
    public function index(string $uuid): AnonymousResourceCollection
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $ctas = Cta::with('imageFile')
            ->where('event_id', $event->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return CtaResource::collection($ctas);
    }

    public function store(string $uuid, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $data = $this->validateCta($request, creating: true);

        $cta = Cta::create($this->payload($data, $event) + [
            'event_id' => $event->id,
            'position' => (int) (Cta::where('event_id', $event->id)->max('position') + 1),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => new CtaResource($cta->load('imageFile'))], 201);
    }

    public function update(string $uuid, string $cta, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $model = Cta::where('event_id', $event->id)->where('uuid', $cta)->firstOrFail();
        $data = $this->validateCta($request, creating: false, currentType: $model->type);

        $model->update($this->payload($data, $event, $model) + ['updated_by' => $request->user()->id]);

        return response()->json(['data' => new CtaResource($model->fresh('imageFile'))]);
    }

    public function destroy(string $uuid, string $cta): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        Cta::where('event_id', $event->id)->where('uuid', $cta)->firstOrFail()->delete();

        return response()->json(['message' => 'CTA deleted.']);
    }

    protected function validateCta(Request $request, bool $creating, ?string $currentType = null): array
    {
        $type = $request->input('type', $currentType);
        $req = $creating ? 'required' : 'sometimes';

        $rules = [
            'type' => [$creating ? 'required' : 'sometimes', 'in:image,video,text'],
            'title' => ['nullable', 'string', 'max:255'],
            'button_label' => ['nullable', 'string', 'max:120'],
            'button_link' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            // RLS scopes `files` to the active org, so exists() also enforces ownership.
            'image_file_id' => ['nullable', 'integer', 'exists:files,id'],
            'videos' => ['nullable', 'array'],
            'videos.*.platform' => ['required_with:videos', 'string', 'max:40'],
            'videos.*.url' => ['required_with:videos', 'string', 'max:500'],
            'videos.*.caption' => ['nullable', 'string', 'max:255'],
        ];

        // An image CTA needs its banner; a video CTA needs at least one link.
        if ($type === 'image' && $creating) {
            $rules['image_file_id'] = ['required', 'integer', 'exists:files,id'];
        }
        if ($type === 'video' && $creating) {
            $rules['videos'] = [$req, 'array', 'min:1'];
        }

        return $request->validate($rules);
    }

    /**
     * Reduce the validated input to the columns that belong to the resolved
     * type, so switching type (or partial updates) never leaves stale fields.
     */
    protected function payload(array $data, Event $event, ?Cta $existing = null): array
    {
        $type = $data['type'] ?? $existing?->type ?? 'image';

        $out = [
            'type' => $type,
            'title' => $data['title'] ?? $existing?->title,
            'button_label' => $data['button_label'] ?? $existing?->button_label,
            'button_link' => $data['button_link'] ?? $existing?->button_link,
            'description' => $type === 'text' ? ($data['description'] ?? $existing?->description) : null,
            'image_file_id' => $type === 'image' ? ($data['image_file_id'] ?? $existing?->image_file_id) : null,
            'videos' => $type === 'video' ? array_values($data['videos'] ?? $existing?->videos ?? []) : null,
        ];

        // Video CTAs have no button; image CTAs have no rich description.
        if ($type === 'video') {
            $out['button_label'] = null;
            $out['button_link'] = null;
        }

        return $out;
    }
}
