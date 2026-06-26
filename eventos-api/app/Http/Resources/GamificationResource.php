<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GamificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'enabled' => (bool) $this->enabled,
            'scores' => (object) ($this->scores ?? []),
            'award_title' => $this->award_title,
            'award_description' => $this->award_description,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
