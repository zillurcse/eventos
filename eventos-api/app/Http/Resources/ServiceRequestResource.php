<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = $this->serviceItem;

        return [
            'id' => $this->uuid,
            'order_number' => $this->whenLoaded('serviceOrder', fn () => $this->serviceOrder->order_number),
            'service_item_id' => $this->service_item_id,
            'name' => $item?->title,
            'unit' => $item?->unit,
            'image' => $item?->image,
            'category' => $item?->category?->name,
            'unit_price' => (float) $this->unit_price,
            'quantity' => (int) $this->quantity,
            'total' => round($this->unit_price * $this->quantity, 2),
            'currency' => $this->currency,
            'status' => $this->status,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
