<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExhibitorPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'kind' => $this->kind,
            'description' => $this->description,
            'tier' => $this->tier,
            'booth_size' => $this->booth_size,
            'price_cents' => (int) $this->price_cents,
            'currency' => $this->currency,
            'entitlements' => $this->entitlements,
            'rank' => (int) $this->rank,
        ];
    }
}
