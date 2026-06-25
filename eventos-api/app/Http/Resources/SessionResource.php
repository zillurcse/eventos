<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $meta = $this->meta ?? [];

        return [
            'id'          => $this->uuid,
            'title'       => $this->title,
            'description' => $this->description,
            'starts_at'   => $this->starts_at?->toIso8601String(),
            'ends_at'     => $this->ends_at?->toIso8601String(),
            'timezone'    => $this->resolvedTimezone(),
            'status'      => $this->status,
            'capacity'    => $this->capacity,
            'stream_url'  => $this->stream_url,

            // Extra fields from meta JSONB
            'session_place'      => $meta['session_place'] ?? null,
            'logo_url'           => $meta['logo_url'] ?? null,
            'tags'               => $meta['tags'] ?? [],
            'is_featured'        => $meta['is_featured'] ?? false,
            'is_allowed_to_rate' => $meta['is_allowed_to_rate'] ?? false,

            // Stream settings from meta JSONB
            'is_stream'                => $meta['is_stream'] ?? false,
            'who_will_host'            => $meta['who_will_host'] ?? null,
            'stream_link'              => $meta['stream_link'] ?? null,
            'on_demand_recording_link' => $meta['on_demand_recording_link'] ?? null,
            'vimeo_live_id'            => $meta['vimeo_live_id'] ?? null,
            'can_live_chat'            => $meta['can_live_chat'] ?? false,
            'can_qa'                   => $meta['can_qa'] ?? false,
            'can_live_polls'           => $meta['can_live_polls'] ?? false,
            'can_attendee_list'        => $meta['can_attendee_list'] ?? false,
            'can_session'              => $meta['can_session'] ?? false,

            'track'    => new TrackResource($this->whenLoaded('track')),
            'room'     => new RoomResource($this->whenLoaded('room')),
            'speakers' => SpeakerResource::collection($this->whenLoaded('speakers')),
        ];
    }
}
