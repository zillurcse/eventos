<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SurveyResource;
use App\Models\Event;
use App\Models\Form;
use App\Models\Survey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

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
            $form = $model->form;
            $form->fields()->delete();
            $this->syncFields($form, $data['questions'] ?? []);
        }

        return response()->json(['data' => new SurveyResource($model->fresh()->load('form.fields.options'))]);
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
