<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'price_cents' => (int) $this->price_cents,
            'currency' => $this->currency,
            'billing_interval' => $this->billing_interval,
            'trial_days' => (int) $this->trial_days,
            'limits' => $this->limits,
            'features' => $this->whenLoaded('features', fn () => $this->features->pluck('key')),
        ];
    }
}
