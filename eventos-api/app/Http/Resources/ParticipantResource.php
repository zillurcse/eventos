<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A person taking part in an event (a participation + its contact). Powers the
 * organizer "Users" screens. Block state is kept in participation.meta.blocked.
 */
class ParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $c = $this->contact;
        $meta = $this->meta ?? [];

        return [
            'id'            => $this->uuid,
            'name'          => $c ? trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: null : null,
            'email'         => $c?->email,
            'phone'         => $c?->phone,
            'company'       => $c?->company,
            'job_title'     => $c?->job_title,
            'role'          => $this->role,
            'status'        => $this->status,
            'blocked'       => (bool) ($meta['blocked'] ?? false),
            'has_login'     => (bool) ($c?->user_id),
            'checked_in'    => $this->status === 'checked_in' || $this->checked_in_at !== null,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'registered_at' => $this->created_at?->toIso8601String(),
            'avatar_url'    => $meta['avatar_url'] ?? ($this->profile_data['avatar_url'] ?? null),
        ];
    }
}
