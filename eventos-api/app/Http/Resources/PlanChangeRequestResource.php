<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanChangeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status,
            'note' => $this->note,
            'review_note' => $this->review_note,
            'requested_plan' => $this->whenLoaded('requestedPlan', fn () => [
                'name' => $this->requestedPlan?->name,
                'slug' => $this->requestedPlan?->slug,
                'price_cents' => (int) $this->requestedPlan?->price_cents,
            ]),
            'current_plan' => $this->whenLoaded('currentPlan', fn () => [
                'name' => $this->currentPlan?->name,
                'slug' => $this->currentPlan?->slug,
                'price_cents' => (int) $this->currentPlan?->price_cents,
            ]),
            'organization' => $this->whenLoaded('organization', fn () => [
                'id' => $this->organization?->uuid,
                'name' => $this->organization?->name,
            ]),
            'requested_by' => $this->whenLoaded('requester', fn () => $this->requester?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
