<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price_cents' => (int) $this->price_cents,
            'currency' => $this->currency,
            'quantity' => $this->quantity,
            'sold' => (int) $this->sold,
            'remaining' => $this->quantity === null ? null : max(0, $this->quantity - $this->sold),
            'min_per_order' => (int) $this->min_per_order,
            'max_per_order' => $this->max_per_order,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
