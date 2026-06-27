<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Snake_case keys mirror what the floor.expouse canvas store expects
 * (normalizeFloorData reads floor_area / dom_elements / wall_generated, etc.).
 */
class FloorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'dimensions' => $this->dimensions,
            'floor_area' => $this->floor_area,
            'shape_type' => $this->shape_type,
            'objects' => $this->objects ?? [],
            'dom_elements' => $this->dom_elements ?? [],
            'offset' => $this->offset,
            'zoom' => (int) $this->zoom,
            'wall_generated' => (bool) $this->wall_generated,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
