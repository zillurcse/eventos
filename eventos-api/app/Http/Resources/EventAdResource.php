<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventAdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'event_id'        => $this->event_id,
            'placement'       => $this->placement,
            'title'           => $this->title,
            'is_active'       => (bool) $this->is_active,
            'images'          => $this->images ?? [],
            'targeted_groups' => $this->targeted_groups ?? [],
            'targeted_pages'  => $this->targeted_pages ?? [],
            'impressions'     => (int) $this->impressions,
            'clicks'          => (int) $this->clicks,
            'ctr'             => $this->impressions > 0 ? round($this->clicks / $this->impressions * 100, 1) : 0,
            'start_at'        => $this->start_at?->toIso8601String(),
            'end_at'          => $this->end_at?->toIso8601String(),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
