<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $logo = $this->logoFile;

        return [
            'id' => $this->uuid,
            'type' => $this->type,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'website' => $this->website,
            'tier_rank' => (int) $this->tier_rank,
            'status' => $this->status,
            'logo_url' => $logo ? Storage::disk($logo->disk)->url($logo->path) : null,
            'profile' => $this->profile_data,
            'package' => new PartnerPackageResource($this->whenLoaded('package')),
            'members' => PartnerMemberResource::collection($this->whenLoaded('members')),
            'products' => PartnerProductResource::collection($this->whenLoaded('products')),
            'members_count' => $this->whenCounted('members'),
        ];
    }
}
