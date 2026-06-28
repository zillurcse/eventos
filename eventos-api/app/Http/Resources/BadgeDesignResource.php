<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Snake_case keys mirror what the badge.expouse editor expects. The editor's
 * setCavasElementData() reads `badge_json` (page_config + front/back boxes) and
 * the ordered `layers` list off the design.
 */
class BadgeDesignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'badge_json' => $this->badge_json ?? (object) [],
            'font_json' => $this->font_json,
            'back_json' => $this->back_json,
            'format' => $this->format,
            'is_default' => (bool) $this->is_default,
            'measurements_type' => $this->measurements_type,
            'width' => $this->width,
            'height' => $this->height,
            'bg_color' => $this->bg_color,
            'bg_image' => $this->bg_image,
            'padding_top' => $this->padding_top,
            'padding_right' => $this->padding_right,
            'padding_bottom' => $this->padding_bottom,
            'padding_left' => $this->padding_left,
            'badge_for' => $this->badge_for,
            'layers' => $this->layers ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
