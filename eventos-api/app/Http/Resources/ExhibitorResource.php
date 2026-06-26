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
            'entitlements' => $profile['entitlements'] ?? [],
            'package' => new ExhibitorPackageResource($this->whenLoaded('package')),
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
            ])->values()),
            'members_count' => $this->whenCounted('members'),
        ]);
    }
}
