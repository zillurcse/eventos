<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'key' => $this->key,
            'name' => $this->name,
            'subject' => $this->subject,
            'from_name' => $this->from_name,
            'from_email' => $this->from_email,
            'status' => $this->status,
            'version' => (int) $this->version,
            'design' => $this->design,
            'has_compiled' => ! empty($this->compiled_html),
        ];
    }
}
