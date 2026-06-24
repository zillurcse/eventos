<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'target_entity' => $this->target_entity,
            'status' => $this->status,
            'version' => (int) $this->version,
            'settings' => $this->settings,
            'fields' => FormFieldResource::collection($this->whenLoaded('fields')),
        ];
    }
}
