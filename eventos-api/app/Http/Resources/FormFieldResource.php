<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'help_text' => $this->help_text,
            'type' => $this->type,
            'required' => (bool) $this->is_required,
            'is_pii' => (bool) $this->is_pii,
            'validation' => $this->validation,
            'default_value' => $this->default_value,
            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn ($o) => [
                'label' => $o->label,
                'value' => $o->value,
            ])->values()),
        ];
    }
}
