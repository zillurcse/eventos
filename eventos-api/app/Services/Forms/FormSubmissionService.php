<?php

namespace App\Services\Forms;

use App\Models\Form;
use App\Models\FormFieldValue;
use App\Models\FormSubmission;
use Illuminate\Support\Facades\DB;

/**
 * Validates a submission against a published form, persists the normalized
 * values, and returns the JSONB projection for the owning entity's profile_data
 * (architecture §3.4, §6.12).
 */
class FormSubmissionService
{
    public function __construct(protected FormValidatorBuilder $validator) {}

    /**
     * @param  array<string,mixed>  $extra  extra submission attributes
     *                                      (source, review_status, meta…)
     * @param  array<int,string>|null  $only  validate only these field keys —
     *                                        the keys the collecting surface actually shows
     * @return array{submission: FormSubmission, projection: array<string,mixed>}
     */
    public function submit(Form $form, array $input, ?string $ownerType = null, ?int $ownerId = null, array $extra = [], ?array $only = null): array
    {
        $validated = $this->validator->validate($form, $input, $only);

        $submission = DB::transaction(function () use ($form, $validated, $ownerType, $ownerId, $extra) {
            $submission = FormSubmission::create([
                'form_id' => $form->id,
                'form_version' => $form->version,
                'event_id' => $form->event_id,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'status' => 'complete',
                'submitted_at' => now(),
            ] + $extra);

            foreach ($form->fields as $field) {
                if ($field->type === 'section_break' || ! array_key_exists($field->key, $validated)) {
                    continue;
                }

                FormFieldValue::create([
                    'submission_id' => $submission->id,
                    'field_id' => $field->id,
                    'value' => json_encode($validated[$field->key]), // jsonb
                ]);
            }

            return $submission;
        });

        return ['submission' => $submission, 'projection' => $this->projection($form, $validated)];
    }

    /** field key => value, ready to merge into an entity's profile_data. */
    public function projection(Form $form, array $validated): array
    {
        $out = [];
        foreach ($form->fields as $field) {
            if ($field->type !== 'section_break' && array_key_exists($field->key, $validated)) {
                $out[$field->key] = $validated[$field->key];
            }
        }

        return $out;
    }
}
