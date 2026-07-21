<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FormFieldValue;
use App\Models\FormSubmission;
use App\Models\Participation;
use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The attendee side of Surveys (the event site's "Surveys" tab). The organizer
 * builds a survey in Engagement › Surveys (SurveyController); here attendees
 * see the ones that are open, answer them, and read back what they submitted.
 *
 * Questions are the linked form's `form_fields` and an answer set is an
 * ordinary `form_submission` + `form_field_values`, with a `survey_responses`
 * row tying the two to the attendee (architecture §6.6/§6.12).
 *
 * Runs behind the `participant` middleware, so the event, org GUC and the
 * caller's participation are already resolved on the request.
 */
class ParticipantSurveyController extends Controller
{
    /** Surveys for this event, each with the viewer's own answer state. */
    public function index(Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $pid = (int) $request->attributes->get('participation_id');

        $surveys = Survey::where('event_id', $eventId)
            ->where('status', 'published')
            ->with('form.fields.options')
            ->orderByDesc('id')
            ->get();

        // One grouped query rather than a per-survey lookup.
        $mine = SurveyResponse::where('event_id', $eventId)
            ->where('participation_id', $pid)
            ->pluck('id', 'survey_id');

        return response()->json([
            'data' => $surveys->map(fn (Survey $s) => $this->payload($s, (int) ($mine[$s->id] ?? 0)))->values(),
        ]);
    }

    /** One survey, plus my answers when I have already responded. */
    public function show(Request $request, string $event, int $survey): JsonResponse
    {
        $model = $this->survey($request, $survey);
        $response = $this->myResponse($request, $model);

        $payload = $this->payload($model, (int) ($response?->id ?? 0));
        $payload['my_answers'] = $response ? $this->answers($response) : null;

        return response()->json(['data' => $payload]);
    }

    /**
     * Answer a survey. One response per attendee — the survey is closed to them
     * afterwards, so this is a create-only endpoint.
     */
    public function store(Request $request, string $event, int $survey): JsonResponse
    {
        $model = $this->survey($request, $survey);
        $pid = (int) $request->attributes->get('participation_id');

        if ($model->phase() !== 'ongoing') {
            throw ValidationException::withMessages([
                'answers' => $model->phase() === 'upcoming'
                    ? 'This survey hasn’t opened yet.'
                    : 'This survey is closed.',
            ]);
        }

        if ($this->myResponse($request, $model)) {
            throw ValidationException::withMessages(['answers' => 'You have already answered this survey.']);
        }

        $fields = $model->form?->fields ?? collect();
        if ($fields->isEmpty()) {
            throw ValidationException::withMessages(['answers' => 'This survey has no questions yet.']);
        }

        $answers = $this->validated($request, $fields);

        $response = DB::transaction(function () use ($request, $model, $pid, $fields, $answers) {
            $submission = FormSubmission::create([
                'form_id' => $model->form_id,
                'form_version' => $model->form->version,
                'event_id' => $model->event_id,
                'status' => 'complete',
                'submitted_at' => now(),
                // An anonymous survey keeps no trail back to the person on the
                // answers themselves; see the note on survey_responses below.
                'submitted_by_contact_id' => $model->is_anonymous
                    ? null
                    : Participation::whereKey($pid)->value('contact_id'),
            ]);

            foreach ($fields as $field) {
                if (! array_key_exists($field->id, $answers)) {
                    continue;
                }

                FormFieldValue::create([
                    'submission_id' => $submission->id,
                    'field_id' => $field->id,
                    'value' => json_encode($answers[$field->id]), // jsonb
                ]);
            }

            // The participation is recorded even on an anonymous survey — it is
            // what stops the same attendee answering twice — but the organizer's
            // results never surface it for those (see SurveyController@responses).
            return SurveyResponse::create([
                'survey_id' => $model->id,
                'event_id' => $model->event_id,
                'participation_id' => $pid,
                'submission_id' => $submission->id,
                'submitted_at' => now(),
            ]);
        });

        $payload = $this->payload($model, (int) $response->id);
        $payload['my_answers'] = $this->answers($response);

        return response()->json(['data' => $payload], 201);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** Resolve a published survey by id, constrained to the request's event. */
    private function survey(Request $request, int $id): Survey
    {
        return Survey::where('event_id', $request->attributes->get('event_id'))
            ->where('status', 'published')
            ->with('form.fields.options')
            ->findOrFail($id);
    }

    private function myResponse(Request $request, Survey $survey): ?SurveyResponse
    {
        return SurveyResponse::where('survey_id', $survey->id)
            ->where('participation_id', (int) $request->attributes->get('participation_id'))
            ->first();
    }

    /**
     * Validate `answers` (keyed by question id) against the survey's questions,
     * and return them keyed the same way, dropping anything not asked for.
     */
    private function validated(Request $request, $fields): array
    {
        $rules = ['answers' => ['required', 'array']];

        foreach ($fields as $field) {
            $key = "answers.{$field->id}";
            $presence = $field->is_required ? 'required' : 'nullable';

            $rules[$key] = match ($field->type) {
                'multiselect' => [$presence, 'array', 'min:'.($field->is_required ? 1 : 0)],
                'date' => [$presence, 'date'],
                'file' => [$presence, 'string', 'url', 'max:2000'],
                'textarea' => [$presence, 'string', 'max:5000'],
                default => [$presence, 'string', 'max:1000'],
            };

            if ($field->type === 'multiselect') {
                $rules["{$key}.*"] = ['string', 'max:180'];
            }
        }

        $data = $request->validate($rules, [], $this->attributeNames($fields));
        $answers = $data['answers'] ?? [];

        $out = [];
        foreach ($fields as $field) {
            $value = $answers[$field->id] ?? null;

            if (in_array($field->type, ['select', 'radio', 'multiselect'], true)) {
                $value = $this->withinOptions($field, $value);
            } elseif (is_string($value)) {
                $value = trim($value) === '' ? null : trim($value);
            }

            if ($value === null || $value === []) {
                if ($field->is_required) {
                    throw ValidationException::withMessages([
                        "answers.{$field->id}" => 'Please answer “'.$field->label.'”.',
                    ]);
                }

                continue;
            }

            $out[$field->id] = $value;
        }

        return $out;
    }

    /** Human labels so validation messages read as the question, not "answers.7". */
    private function attributeNames($fields): array
    {
        $names = [];
        foreach ($fields as $field) {
            $names["answers.{$field->id}"] = $field->label ?? 'answer';
        }

        return $names;
    }

    /** Keep only choices the organizer actually offered for this question. */
    private function withinOptions($field, mixed $value): mixed
    {
        $allowed = $field->options->pluck('value')->filter()->all();

        if ($field->type === 'multiselect') {
            $picked = array_values(array_intersect((array) $value, $allowed));

            return $picked ?: null;
        }

        return in_array($value, $allowed, true) ? $value : null;
    }

    /** A response's answers, keyed by question id, ready to re-render the form. */
    private function answers(SurveyResponse $response): array
    {
        if (! $response->submission_id) {
            return [];
        }

        return FormFieldValue::where('submission_id', $response->submission_id)
            ->pluck('value', 'field_id')
            ->map(fn ($v) => json_decode((string) $v, true))
            ->all();
    }

    /** A survey as the attendee sees it: the questions plus what I may do now. */
    private function payload(Survey $survey, int $myResponseId): array
    {
        $phase = $survey->phase();
        $fields = $survey->form?->fields ?? collect();

        return [
            'id' => $survey->id,
            'title' => $survey->title,
            'description' => $survey->form?->description,
            'phase' => $phase,
            'is_anonymous' => (bool) $survey->is_anonymous,
            'opens_at' => $survey->opens_at?->toIso8601String(),
            'closes_at' => $survey->closes_at?->toIso8601String(),
            'questions_count' => $fields->count(),
            'questions' => $fields->map(fn ($f) => [
                'id' => $f->id,
                'label' => $f->label,
                'help_text' => $f->help_text,
                'type' => $f->type,
                'is_required' => (bool) $f->is_required,
                'options' => $f->options->map(fn ($o) => [
                    'label' => $o->label,
                    'value' => $o->value ?? $o->label,
                ])->values(),
            ])->values(),
            'has_responded' => $myResponseId > 0,
            'can_respond' => $phase === 'ongoing' && $myResponseId === 0 && $fields->isNotEmpty(),
        ];
    }
}
