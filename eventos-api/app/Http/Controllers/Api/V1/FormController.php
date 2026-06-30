<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FormResource;
use App\Models\Event;
use App\Models\Form;
use App\Services\Forms\FormSubmissionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class FormController extends Controller
{
    // ── Builder (organizer, tenant-scoped) ──────────────────────────

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Form::with('fields')->latest('id');

        if ($request->filled('event')) {
            $event = Event::where('uuid', $request->string('event'))->firstOrFail();
            $query->where('event_id', $event->id);
        }

        return FormResource::collection($query->get());
    }

    public function show(string $uuid): JsonResponse
    {
        $form = Form::with('fields.options')->where('uuid', $uuid)->firstOrFail();

        return response()->json(['data' => new FormResource($form)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'key' => ['nullable', 'string', 'max:60'],
            'event' => ['nullable', 'string'], // bind to an event (uuid) — e.g. registration
            'target_entity' => ['nullable', 'in:contact,participation,exhibitor,survey'],
            'fields' => ['array'],
            'fields.*.key' => ['required_with:fields', 'string', 'max:60'],
            'fields.*.type' => ['required_with:fields', 'string', 'max:30'],
        ]);

        $key = $data['key'] ?? 'custom';
        $eventId = ! empty($data['event'])
            ? Event::where('uuid', $data['event'])->firstOrFail()->id
            : null;

        $form = Form::create([
            'name' => $data['name'],
            'key' => $key,
            'event_id' => $eventId,
            'target_entity' => $data['target_entity'] ?? null,
            'status' => 'draft',
            'version' => 1,
            'created_by' => $request->user()->id,
        ]);

        // Use the FULL field payload (validate() strips nested keys without
        // rules), or seed the default set for this entity from config.
        $fields = $request->input('fields', []);
        if (empty($fields)) {
            $fields = config("eventos.default_fields.{$key}", []);
        }
        $this->syncFields($form, $fields);

        return response()->json(['data' => new FormResource($form->load('fields.options'))], 201);
    }

    /** Edit a draft form: rename and/or replace its fields. */
    public function update(string $uuid, Request $request): JsonResponse
    {
        $form = Form::where('uuid', $uuid)->firstOrFail();

        $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'fields' => ['sometimes', 'array'],
            'fields.*.key' => ['required_with:fields', 'string', 'max:60'],
            'fields.*.type' => ['required_with:fields', 'string', 'max:30'],
        ]);

        if ($request->filled('name')) {
            $form->update(['name' => $request->string('name')]);
        }
        if ($request->has('fields')) {
            $form->fields()->delete();
            $this->syncFields($form, $request->input('fields', []));
        }

        return response()->json(['data' => new FormResource($form->fresh()->load('fields.options'))]);
    }

    public function publish(string $uuid): JsonResponse
    {
        $form = Form::where('uuid', $uuid)->firstOrFail();
        $form->update(['status' => 'published', 'version' => $form->version + 1]);

        return response()->json(['data' => new FormResource($form->load('fields.options'))]);
    }

    // ── Public (render token = form uuid; no auth) ──────────────────

    public function render(string $uuid): JsonResponse
    {
        $form = $this->loadPublished($uuid);

        return response()->json(['data' => new FormResource($form)]);
    }

    public function submit(string $uuid, Request $request, FormSubmissionService $service): JsonResponse
    {
        $form = $this->loadPublished($uuid);
        $this->activateTenant($form);

        $result = $service->submit($form, $request->all());

        return response()->json([
            'submission_id' => $result['submission']->uuid,
            'projection' => $result['projection'],
        ], 201);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /** Load a published form by its public render token, bypassing RLS. */
    protected function loadPublished(string $uuid): Form
    {
        return Form::on('pgsql_admin')
            ->with('fields.options')
            ->where('uuid', $uuid)
            ->where('status', 'published')
            ->firstOrFail();
    }

    /** A public submission runs in the form's own tenant context. */
    protected function activateTenant(Form $form): void
    {
        app(TenantContext::class)->set($form->organization_id);
        DB::statement("set app.current_organization = '{$form->organization_id}'");
    }

    protected function syncFields(Form $form, array $fields): void
    {
        $sort = 0;
        foreach ($fields as $f) {
            $field = $form->fields()->create([
                'key' => $f['key'],
                'label' => $f['label'] ?? null,
                'help_text' => $f['help_text'] ?? null,
                'type' => $f['type'],
                'is_required' => $f['is_required'] ?? false,
                'is_unique' => $f['is_unique'] ?? false,
                'is_pii' => $f['is_pii'] ?? false,
                'validation' => $f['validation'] ?? null,
                'default_value' => $f['default_value'] ?? null,
                'sort_order' => $sort++,
            ]);

            foreach ($f['options'] ?? [] as $i => $opt) {
                $field->options()->create([
                    'label' => $opt['label'],
                    'value' => $opt['value'] ?? $opt['label'],
                    'sort_order' => $i,
                ]);
            }
        }
    }
}
