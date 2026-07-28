<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ExpoLensUploadLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Organizer-side management of photographer upload links.
 *
 * The token is stored in the clear so the organizer can re-copy the link later
 * (it is a capability URL, not a credential); safety comes from it being
 * event-scoped, revocable, expirable and upload-capped.
 */
class ExpoLensUploadLinkController extends Controller
{
    public function index(string $uuid): JsonResponse
    {
        $event = $this->event($uuid);

        $links = ExpoLensUploadLink::where('event_id', $event->id)
            ->latest()
            ->get()
            ->map(fn (ExpoLensUploadLink $link) => $this->linkData($link));

        return response()->json(['data' => $links]);
    }

    public function store(string $uuid, Request $request): JsonResponse
    {
        $event = $this->event($uuid);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:150'],
            'album' => ['nullable', 'string', 'max:120'],
            'auto_approve' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'max_uploads' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $link = ExpoLensUploadLink::create([
            'event_id' => $event->id,
            'token' => Str::random(48),
            'label' => $data['label'],
            'album' => $data['album'] ?? null,
            'auto_approve' => $data['auto_approve'] ?? false,
            'expires_at' => $data['expires_at'] ?? null,
            'max_uploads' => $data['max_uploads'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->linkData($link)], 201);
    }

    public function update(string $uuid, string $link, Request $request): JsonResponse
    {
        $event = $this->event($uuid);
        $model = $this->link($event, $link);
        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:150'],
            'album' => ['sometimes', 'nullable', 'string', 'max:120'],
            'auto_approve' => ['sometimes', 'boolean'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'max_uploads' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
            'revoked' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('revoked', $data)) {
            $model->revoked_at = $data['revoked'] ? now() : null;
            unset($data['revoked']);
        }

        $model->fill($data)->save();

        return response()->json(['data' => $this->linkData($model->fresh())]);
    }

    public function destroy(string $uuid, string $link): JsonResponse
    {
        $event = $this->event($uuid);
        $this->link($event, $link)->delete();

        return response()->json(['message' => 'Upload link deleted.']);
    }

    private function linkData(ExpoLensUploadLink $link): array
    {
        return [
            'id' => $link->uuid,
            'label' => $link->label,
            'album' => $link->album,
            'token' => $link->token,
            'auto_approve' => $link->auto_approve,
            'expires_at' => $link->expires_at?->toIso8601String(),
            'max_uploads' => $link->max_uploads,
            'uploads_count' => $link->uploads_count,
            'remaining' => $link->remaining(),
            'revoked' => $link->isRevoked(),
            'usable' => $link->isUsable(),
            'last_used_at' => $link->last_used_at?->toIso8601String(),
            'created_at' => $link->created_at?->toIso8601String(),
        ];
    }

    private function event(string $uuid): Event
    {
        return Event::where('uuid', $uuid)->firstOrFail();
    }

    private function link(Event $event, string $uuid): ExpoLensUploadLink
    {
        return ExpoLensUploadLink::where('event_id', $event->id)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
