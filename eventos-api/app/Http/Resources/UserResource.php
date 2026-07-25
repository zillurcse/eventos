<?php

namespace App\Http\Resources;

use App\Models\Exhibitor;
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
            'must_change_password' => (bool) $this->must_change_password,
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
                // Enabled Showcase feature keys for the exhibitor-admin SPA.
                // null  = never configured (profile + package empty) → SPA allows all
                // []    = configured but nothing enabled → SPA hides/guards everything gated
                // […]  = only these keys are allowed
                'entitlements' => $this->enabledFeatureKeys($em->exhibitor),
            ])->values(),
        ];
    }

    /**
     * Resolve the exhibitor's enabled feature keys for the exhibitor-admin SPA.
     *
     * Booth Permissions (profile_data.entitlements) win per key. Keys missing
     * from an older/shorter freeze still fall back to the package — so newly
     * added catalogue entries (Leads & analytics) are not silently denied.
     *
     * @return list<string>|null null = never configured → SPA allows all
     */
    protected function enabledFeatureKeys(?Exhibitor $exhibitor): ?array
    {
        if (! $exhibitor) {
            return null;
        }

        $saved = $exhibitor->profile_data['entitlements'] ?? null;
        $package = $exhibitor->relationLoaded('package')
            ? $exhibitor->package
            : $exhibitor->package()->first();
        $fromPackage = $package?->entitlements;

        $hasSaved = is_array($saved) && $saved !== [];
        $hasPackage = is_array($fromPackage) && $fromPackage !== [];

        if (! $hasSaved && ! $hasPackage) {
            return null;
        }

        // Package as base; saved lines overlay by key (including explicit off).
        $byKey = [];
        if ($hasPackage) {
            foreach ($fromPackage as $line) {
                if (is_array($line) && isset($line['key'])) {
                    $byKey[$line['key']] = $line;
                }
            }
        }
        if ($hasSaved) {
            foreach ($saved as $line) {
                if (is_array($line) && isset($line['key'])) {
                    $byKey[$line['key']] = $line;
                }
            }
        }

        return collect($byKey)
            ->filter(fn ($f) => (bool) ($f['enabled'] ?? false))
            ->keys()
            ->values()
            ->all();
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
