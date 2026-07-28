<?php

namespace App\Http\Resources;

use App\Support\GamificationActions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GamificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'enabled' => (bool) $this->enabled,
            'scores' => (object) $this->resolvedScores(),
            // Dynamic Point Scoring catalogue — admin UI renders from this, not a
            // hardcoded list, so keys stay aligned with the runtime scorer.
            'actions' => GamificationActions::all(),
            'award_title' => $this->award_title,
            'award_description' => $this->award_description,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
