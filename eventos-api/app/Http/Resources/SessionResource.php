<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            // UTC instants — the API never pre-localizes (§6.3.1); the client
            // converts using `timezone` below.
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'timezone' => $this->resolvedTimezone(),
            'status' => $this->status,
            'capacity' => $this->capacity,
            'stream_url' => $this->stream_url,
            'track' => new TrackResource($this->whenLoaded('track')),
            'room' => new RoomResource($this->whenLoaded('room')),
            'speakers' => SpeakerResource::collection($this->whenLoaded('speakers')),
        ];
    }
}
