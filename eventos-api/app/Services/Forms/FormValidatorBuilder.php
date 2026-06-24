<?php

namespace App\Services\Forms;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Support\Facades\Validator;

/**
 * Builds a Laravel validator from a form's field definitions (architecture
 * §6.12) — the server-side enforcement behind the dynamic form builder.
 */
class FormValidatorBuilder
{
    /** @return array<string, array<int,string>> rules keyed by field key */
    public function rules(Form $form): array
    {
        $rules = [];

        foreach ($form->fields as $field) {
            if ($field->type === 'section_break') {
                continue;
            }
            $rules[$field->key] = $this->rulesFor($field);
        }

        return $rules;
    }

    public function validate(Form $form, array $input): array
    {
        return Validator::make($input, $this->rules($form), $this->messages($form))->validate();
    }

    protected function rulesFor(FormField $field): array
    {
        $rules = [$field->is_required ? 'required' : 'nullable'];
        $v = $field->validation ?? [];

        match ($field->type) {
            'email' => $rules[] = 'email:rfc',
            'number', 'rating' => $rules = array_merge($rules, $this->numeric($v)),
            'date' => $rules[] = 'date',
            'select', 'radio' => $rules = array_merge($rules, $this->choice($field)),
            'multiselect', 'checkbox' => $rules[] = 'array',
            'file' => $rules[] = 'string', // file id reference after upload
            default => $rules = array_merge($rules, $this->text($v)), // text|textarea|phone
        };

        return $rules;
    }

    protected function numeric(array $v): array
    {
        $r = ['numeric'];
        isset($v['min']) && $r[] = 'min:'.$v['min'];
        isset($v['max']) && $r[] = 'max:'.$v['max'];

        return $r;
    }

    protected function text(array $v): array
    {
        $r = ['string'];
        isset($v['min']) && $r[] = 'min:'.$v['min'];
        isset($v['max']) && $r[] = 'max:'.$v['max'];
        ! empty($v['regex']) && $r[] = 'regex:'.$v['regex'];

        return $r;
    }

    protected function choice(FormField $field): array
    {
        $options = $field->options->pluck('value')->filter()->values()->all();

        return $options ? ['string', 'in:'.implode(',', $options)] : ['string'];
    }

    protected function messages(Form $form): array
    {
        $messages = [];
        foreach ($form->fields as $field) {
            $label = $field->label ?: $field->key;
            $messages[$field->key.'.required'] = "{$label} is required.";
        }

        return $messages;
    }
}
