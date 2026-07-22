<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $design = $this->design ?? [];

        return [
            'id' => $this->uuid,
            'key' => $this->key,
            'category' => $this->category ?: 'custom',
            'name' => $this->name,
            'subject' => $this->subject,
            'preheader' => $this->preheader,
            'from_name' => $this->from_name,
            'from_email' => $this->from_email,
            'reply_to' => $this->reply_to,
            'status' => $this->status,
            'version' => (int) $this->version,
            'blocks' => $design['blocks'] ?? [],
            'settings' => $design['settings'] ?? [],
            // The index omits the large compiled_html column and selects this
            // flag instead; a single-model load still carries the column itself.
            'has_compiled' => $this->resource->getAttribute('has_compiled') !== null
                ? (bool) $this->resource->getAttribute('has_compiled')
                : ! empty($this->compiled_html),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
