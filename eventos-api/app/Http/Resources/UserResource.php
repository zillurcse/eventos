<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $exhibitorMembers = $this->relationLoaded('exhibitorMemberships')
            ? $this->exhibitorMemberships
            : new Collection;

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'is_platform_staff' => (bool) $this->is_platform_staff,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'personas' => $this->personas($exhibitorMembers),
            'memberships' => MembershipResource::collection($this->whenLoaded('memberships')),
            'exhibitors' => $exhibitorMembers->map(fn ($em) => [
                'id' => $em->exhibitor?->uuid,
                'name' => $em->exhibitor?->name,
                'type' => $em->exhibitor?->type,          // exhibitor | sponsor
                'role' => $em->role,                       // admin | staff
                'status' => $em->exhibitor?->status,
                'organization' => $em->exhibitor?->organization?->name,
                'event' => $em->exhibitor?->event?->name,
            ])->values(),
        ];
    }

    /** Classify the signed-in persona(s) so the SPA can route. */
    protected function personas(Collection $exhibitorMembers): array
    {
        $personas = [];

        if ($this->is_platform_staff) {
            $personas[] = 'platform';
        }
        if ($this->relationLoaded('memberships') && $this->memberships->firstWhere('status', 'active')) {
            $personas[] = 'organizer';
        }
        if ($exhibitorMembers->isNotEmpty()) {
            $personas[] = 'exhibitor';
        }

        return $personas;
    }
}
