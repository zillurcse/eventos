<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ExhibitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $logo = $this->logoFile;
        $profile = $this->profile_data ?? [];

        // Core columns are authoritative; the profile_data fields (address,
        // social, cta, spotlight, flags, entitlements, …) are spread flat so the
        // edit drawer can read them as p.<field>.
        return array_merge($profile, [
            'id' => $this->uuid,
            'type' => $this->type,
            'name' => $this->name,
            'email' => $this->email,
            'slug' => $this->slug,
            'description' => $this->description,
            'website_url' => $profile['website_url'] ?? $this->website,
            'tier_rank' => (int) $this->tier_rank,
            'status' => $this->status,
            'package_id' => $this->package_id,
            'logo_url' => $logo ? Storage::disk($logo->disk)->url($logo->path) : null,
            'logo_file_id' => $this->logo_file_id,
            // Resolved for the Permissions tab: saved overrides → package defaults.
            // null = never configured (SPA treats as full access).
            'entitlements' => $this->resolvedEntitlements($profile),
            'package' => $this->whenLoaded('package', fn () => new ExhibitorPackageResource($this->package)),
            'members' => ExhibitorMemberResource::collection($this->whenLoaded('members')),
            'products' => ExhibitorProductResource::collection($this->whenLoaded('products')),
            'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($d) => [
                'id' => $d->id,
                'title' => $d->title,
                'url' => $d->url,
                'visibility' => $d->visibility,
            ])->values()),
            'projects' => $this->whenLoaded('projects', fn () => $this->projects->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'status' => $p->status,
                'meta' => $p->meta,
            ])->values()),
            'members_count' => $this->whenCounted('members'),
        ]);
    }

    /**
     * FeatureLine[] for the organizer Permissions UI / exhibitor gate.
     * Package as base; booth Permissions overlay per key. Keys absent from an
     * older freeze still come from the package (e.g. Leads & analytics).
     *
     * @return list<array{key?: string, enabled?: bool, limit?: int}>|null
     */
    protected function resolvedEntitlements(array $profile): ?array
    {
        $saved = $profile['entitlements'] ?? null;
        $package = $this->relationLoaded('package')
            ? $this->package
            : $this->package()->first();
        $fromPackage = $package?->entitlements;

        $hasSaved = is_array($saved) && $saved !== [];
        $hasPackage = is_array($fromPackage) && $fromPackage !== [];

        if (! $hasSaved && ! $hasPackage) {
            return null;
        }

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

        return array_values($byKey);
    }
}
