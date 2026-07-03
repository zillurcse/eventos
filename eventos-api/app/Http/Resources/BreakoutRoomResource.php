<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BreakoutRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'uuid'              => $this->uuid,
            'event_id'          => $this->event_id,
            'name'              => $this->name,
            'description'       => $this->description,
            'purpose'           => $this->purpose,
            'type'              => $this->type,
            'access_type'       => $this->access_type,
            // Only signal that a code is set; expose the code itself to managers.
            'has_access_code'   => filled($this->access_code),
            'access_code'       => $this->when(
                (bool) $request->user()?->hasPermission('events.manage'),
                $this->access_code,
            ),
            'capacity'          => $this->capacity,
            'poster_url'        => $this->poster_url,
            'provider'          => $this->provider,
            'meeting_url'       => $this->meeting_url,
            'recording_enabled' => (bool) $this->recording_enabled,
            'status'            => $this->status,
            'published_at'      => $this->published_at?->toIso8601String(),
            'starts_at'         => $this->starts_at?->toIso8601String(),
            'ends_at'           => $this->ends_at?->toIso8601String(),
            'meta'              => $this->meta ?? [],
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
