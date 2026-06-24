<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'is_lead_capturer' => (bool) $this->is_lead_capturer,
            'contact' => $this->whenLoaded('contact', fn () => [
                'name' => trim(($this->contact->first_name ?? '').' '.($this->contact->last_name ?? '')) ?: null,
                'email' => $this->contact->email,
                'can_login' => $this->contact->user_id !== null,
            ]),
        ];
    }
}
