<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParticipationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'role' => $this->role,
            'status' => $this->status,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'contact' => $this->whenLoaded('contact', fn () => [
                'name' => trim(($this->contact->first_name ?? '').' '.($this->contact->last_name ?? '')) ?: null,
                'email' => $this->contact->email,
            ]),
            'profile' => $this->profile_data,
        ];
    }
}
