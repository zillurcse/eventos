<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CtaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $image = $this->imageFile;

        return [
            'id' => $this->uuid,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'button_label' => $this->button_label,
            'button_link' => $this->button_link,
            'image_file_id' => $this->image_file_id,
            'image_url' => $image ? Storage::disk($image->disk)->url($image->path) : null,
            'videos' => $this->videos ?? [],
            'position' => $this->position,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
