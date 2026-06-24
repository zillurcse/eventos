<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $partnerMembers = $this->relationLoaded('partnerMemberships')
            ? $this->partnerMemberships
            : new Collection;

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'is_platform_staff' => (bool) $this->is_platform_staff,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'personas' => $this->personas($partnerMembers),
            'memberships' => MembershipResource::collection($this->whenLoaded('memberships')),
            'partners' => $partnerMembers->map(fn ($pm) => [
                'id' => $pm->partner?->uuid,
                'name' => $pm->partner?->name,
                'type' => $pm->partner?->type,            // exhibitor | sponsor
                'role' => $pm->role,                       // admin | staff
                'status' => $pm->partner?->status,
                'organization' => $pm->partner?->organization?->name,
                'event' => $pm->partner?->event?->name,
            ])->values(),
        ];
    }

    /** Classify the signed-in persona(s) so the SPA can route. */
    protected function personas(Collection $partnerMembers): array
    {
        $personas = [];

        if ($this->is_platform_staff) {
            $personas[] = 'platform';
        }
        if ($this->relationLoaded('memberships') && $this->memberships->firstWhere('status', 'active')) {
            $personas[] = 'organizer';
        }
        if ($partnerMembers->isNotEmpty()) {
            $personas[] = 'partner';
        }

        return $personas;
    }
}
