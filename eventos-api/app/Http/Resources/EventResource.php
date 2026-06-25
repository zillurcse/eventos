<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cover = $this->coverFile;

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'format' => $this->format,
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'resolved_timezone' => $this->resolvedTimezone(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_public' => (bool) $this->is_public,
            'location' => $this->meta['location'] ?? null,
            'cover_url' => $cover ? Storage::disk($cover->disk)->url($cover->path) : null,
            'sessions_count' => $this->whenCounted('sessions'),
            'created_at' => $this->created_at,
        ];
    }
}
