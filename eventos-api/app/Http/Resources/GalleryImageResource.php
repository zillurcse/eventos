<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'file_id' => $this->file_id,
            'url' => $this->url,
            'caption' => $this->caption,
            'album' => $this->album ?: 'General',
            'sort_order' => (int) $this->sort_order,
            'is_featured' => (bool) $this->is_featured,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
