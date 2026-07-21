<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SurveyResource;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormFieldValue;
use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Surveys (Event Engagement). A survey's questions are a `form` (key=survey)
 * with form_fields — this controller manages the Survey row and keeps its
 * form's fields in sync, so the generic form builder engine (FormController)
 * stays the single source of truth for question types/options/rendering.
 * Event-scoped on index/store; id-based on show/update/destroy, mirroring
 * the ContestController conventions.
 */
class SurveyController extends Controller
{
    private const FIELD_TYPES = ['text', 'textarea', 'date', 'select', 'multiselect', 'radio', 'file'];

    public function index(string $uuid): AnonymousResourceCollection
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $surveys = Survey::where('event_id', $event->id)
            ->with('form.fields.options')
            ->withCount('responses')
            ->orderByDesc('id')
            ->get();

        return SurveyResource::collection($surveys);
    }

    public function store(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $this->rules($request, required: true);

        $form = Form::create([
            'event_id' => $event->id,
            'key' => 'survey',
            'target_entity' => 'survey',
            'name' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => 'published',
            'version' => 1,
            'created_by' => $request->user()?->id,
        ]);
        $this->syncFields($form, $data['questions'] ?? []);

        $survey = Survey::create([
            'event_id' => $event->id,
            'form_id' => $form->id,
            'title' => $data['title'],
            'is_anonymous' => $data['is_anonymous'] ?? false,
            'status' => 'published',
            'opens_at' => $data['opens_at'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
        ]);

        return response()->json(['data' => new SurveyResource($survey->load('form.fields.options'))], 201);
    }

    public function show(int $survey): JsonResponse
    {
        $model = Survey::with('form.fields.options')->findOrFail($survey);

        return response()->json(['data' => new SurveyResource($model)]);
    }

    public function update(Request $request, int $survey): JsonResponse
    {
        $model = Survey::findOrFail($survey);

        $data = $this->rules($request, required: false);

        if (array_key_exists('title', $data)) {
            $model->title = $data['title'];
        }
        if (array_key_exists('is_anonymous', $data)) {
            $model->is_anonymous = $data['is_anonymous'];
        }
        if ($request->has('opens_at')) {
            $model->opens_at = $data['opens_at'] ?? null;
        }
        if ($request->has('closes_at')) {
            $model->closes_at = $data['closes_at'] ?? null;
        }
        $model->save();

        if ($request->has('description')) {
            $model->form->update(['description' => $data['description'] ?? null]);
        }
        if (array_key_exists('title', $data)) {
            $model->form->update(['name' => $model->title]);
        }
        if ($request->has('questions')) {
            // Rewriting the questions drops the form_fields, and their values
            // cascade with them — so a survey people have answered is frozen.
            if (SurveyResponse::where('survey_id', $model->id)->exists()) {
                throw ValidationException::withMessages([
                    'questions' => 'Attendees have already answered this survey, so its questions can no longer be changed.',
                ]);
            }

            $form = $model->form;
            $form->fields()->delete();
            $this->syncFields($form, $data['questions'] ?? []);
        }

        return response()->json(['data' => new SurveyResource($model->fresh()->load('form.fields.options'))]);
    }

    /**
     * Results: a per-question roll-up plus the individual responses attendees
     * submitted on the event site (ParticipantSurveyController). An anonymous
     * survey still stores which participation answered — that's what enforces
     * one response each — but the respondent is never named back here.
     */
    public function responses(int $survey): JsonResponse
    {
        $model = Survey::with('form.fields.options')->withCount('responses')->findOrFail($survey);
        $fields = $model->form?->fields ?? collect();

        $responses = SurveyResponse::where('survey_id', $model->id)
            ->with('participation.contact')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        // field_id => [submission_id => value], decoded once for both views.
        $values = FormFieldValue::whereIn('submission_id', $responses->pluck('submission_id')->filter())
            ->get()
            ->groupBy('field_id')
            ->map(fn ($rows) => $rows->mapWithKeys(
                fn ($r) => [$r->submission_id => json_decode((string) $r->value, true)]
            ));

        return response()->json([
            'data' => [
                'survey' => new SurveyResource($model),
                'total' => $responses->count(),
                'questions' => $fields->map(function ($field) use ($values, $responses) {
                    $answers = $values[$field->id] ?? collect();
                    $answered = $answers->filter(fn ($v) => $v !== null && $v !== []);

                    return [
                        'id' => $field->id,
                        'label' => $field->label,
                        'type' => $field->type,
                        'answered' => $answered->count(),
                        'options' => in_array($field->type, ['select', 'radio', 'multiselect'], true)
                            ? $this->optionTally($field, $answered, $responses->count())
                            : [],
                        // Free text / dates / files are listed rather than tallied.
                        'answers' => in_array($field->type, ['select', 'radio', 'multiselect'], true)
                            ? []
                            : $answered->values()->take(200)->all(),
                    ];
                })->values(),
                'responses' => $responses->map(fn (SurveyResponse $r) => [
                    'id' => $r->id,
                    'respondent' => $model->is_anonymous ? null : $this->respondentName($r),
                    'submitted_at' => $r->submitted_at?->toIso8601String(),
                    'answers' => $fields->mapWithKeys(fn ($f) => [
                        $f->id => ($values[$f->id] ?? collect())[$r->submission_id] ?? null,
                    ]),
                ])->values(),
            ],
        ]);
    }

    public function destroy(int $survey): JsonResponse
    {
        Survey::findOrFail($survey)->delete();

        return response()->json(['status' => 'success']);
    }

    /** Validate the request; `required` toggles create vs partial-update rules. */
    private function rules(Request $request, bool $required): array
    {
        $req = $required ? 'required' : 'sometimes';

        return $request->validate([
            'title' => [$req, 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_anonymous' => ['nullable', 'boolean'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'questions' => ['sometimes', 'array'],
            'questions.*.label' => ['required_with:questions', 'string', 'max:180'],
            'questions.*.type' => ['required_with:questions', 'string', Rule::in(self::FIELD_TYPES)],
            'questions.*.is_required' => ['nullable', 'boolean'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.options.*.label' => ['required_with:questions.*.options', 'string', 'max:180'],
        ]);
    }

    /** How often each offered choice was picked, as a count and a share. */
    private function optionTally($field, $answers, int $total): array
    {
        return $field->options->map(function ($option) use ($answers, $total) {
            $value = $option->value ?? $option->label;

            $count = $answers->filter(fn ($a) => is_array($a)
                ? in_array($value, $a, true)
                : $a === $value)->count();

            return [
                'label' => $option->label,
                'value' => $value,
                'count' => $count,
                'percent' => $total > 0 ? round($count / $total * 100) : 0,
            ];
        })->values()->all();
    }

    private function respondentName(SurveyResponse $response): ?string
    {
        $contact = $response->participation?->contact;
        if (! $contact) {
            return null;
        }

        return trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')) ?: $contact->email;
    }

    /** Replace a form's questions with the given ordered list. */
    private function syncFields(Form $form, array $questions): void
    {
        foreach ($questions as $i => $q) {
            $field = $form->fields()->create([
                'key' => 'q'.($i + 1),
                'label' => $q['label'] ?? null,
                'type' => $q['type'] ?? 'text',
                'is_required' => $q['is_required'] ?? false,
                'sort_order' => $i,
            ]);

            foreach ($q['options'] ?? [] as $oi => $opt) {
                $field->options()->create([
                    'label' => $opt['label'],
                    'value' => $opt['value'] ?? $opt['label'],
                    'sort_order' => $oi,
                ]);
            }
        }
    }
}
