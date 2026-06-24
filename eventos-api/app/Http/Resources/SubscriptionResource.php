<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status,
            'gateway' => $this->gateway,
            'quantity' => (int) $this->quantity,
            'current_period_start' => $this->current_period_start,
            'current_period_end' => $this->current_period_end,
            'cancel_at_period_end' => (bool) $this->cancel_at_period_end,
            'plan' => new PlanResource($this->whenLoaded('plan')),
        ];
    }
}
