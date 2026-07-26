<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpoLensPhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'file_id' => $this->file_id,
            'url' => $this->url,
            'caption' => $this->caption,
            'album' => $this->album ?: 'General',
            'processing_status' => $this->processing_status,
            'moderation_status' => $this->moderation_status,
            'faces_count' => (int) $this->faces_count,
            'matches_count' => $this->whenCounted('matches'),
            'failure_reason' => $this->failure_reason,
            'photographer' => $this->photographer_meta,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
