<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A speaker is a participation (role=speaker) on the session_speaker pivot.
 * Speaker-specific attributes live in participation.profile_data (form builder).
 */
class SpeakerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => trim(($this->contact?->first_name ?? '').' '.($this->contact?->last_name ?? '')) ?: null,
            'role' => $this->whenPivotLoaded('session_speaker', fn () => $this->pivot->role),
            'profile' => $this->profile_data,
        ];
    }
}
