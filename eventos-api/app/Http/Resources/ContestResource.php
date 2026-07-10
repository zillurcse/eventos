<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'event_id' => $this->event_id,
            'title' => $this->title,
            'contest_type' => $this->contest_type,
            'phase' => $this->phase(),
            'description' => $this->description,
            'description_file_url' => $this->description_file_url,
            'description_file_name' => $this->description_file_name,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'banner_url' => $this->banner_url,
            'caption' => $this->caption,
            'character_limit' => $this->character_limit,
            'points_for_entry' => $this->points_for_entry,
            'points_for_response' => $this->points_for_response,
            'allow_photos' => (bool) $this->allow_photos,
            'allow_videos' => (bool) $this->allow_videos,
            'allow_selfie' => (bool) $this->allow_selfie,
            'winner_chooser' => $this->winner_chooser,
            'winner_number' => $this->winner_number,
            'winning_points' => $this->winning_points,
            'equal_points_distribution' => (bool) $this->equal_points_distribution,
            'attach_mandatory' => (bool) $this->attach_mandatory,
            'allow_multiple_entries' => (bool) $this->allow_multiple_entries,
            'allow_moderate_entries' => (bool) $this->allow_moderate_entries,
            'attendees_can_see_others_entries' => (bool) $this->attendees_can_see_others_entries,
            'attendees_can_see_other_comments' => (bool) $this->attendees_can_see_other_comments,
            'meta' => $this->meta ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
