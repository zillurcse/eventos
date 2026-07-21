<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SurveyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fields = $this->form?->fields ?? collect();

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'form_id' => $this->form_id,
            'title' => $this->title,
            'description' => $this->form?->description,
            'phase' => $this->phase(),
            'is_anonymous' => (bool) $this->is_anonymous,
            'opens_at' => $this->opens_at?->toIso8601String(),
            'closes_at' => $this->closes_at?->toIso8601String(),
            'questions_count' => count($fields),
            'response_count' => (int) ($this->responses_count ?? 0),
            // Not FormFieldResource: the builder round-trips a question by id and
            // needs `is_required` back under the name it posts it with.
            'questions' => collect($fields)->map(fn ($f) => [
                'id' => $f->id,
                'label' => $f->label,
                'type' => $f->type,
                'is_required' => (bool) $f->is_required,
                'options' => $f->options->map(fn ($o) => [
                    'label' => $o->label,
                    'value' => $o->value,
                ])->values(),
            ])->values(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
