<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FormResource;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormFieldValue;
use App\Models\FormSubmission;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Event Settings › Profile — one profile form per audience (attendee, speaker,
 * exhibitor, sponsor, organizer), auto-provisioned from config defaults and
 * edited in the drag-and-drop builder.
 *
 * Each form doubles as an application funnel: the organizer shares its public
 * URL (or embeds it), submissions land as `pending`, and approving one turns
 * the submitter into a real contact + participation with the projected profile
 * — the same pieces public registration uses (§6.4, §6.12).
 *
 * Field sync is an upsert by `key`, not delete-and-recreate: form_field_values
 * cascade from form_fields, so recreating fields would silently orphan every
 * past submission's answers. Seeded default fields can be hidden but never
 * deleted — downstream projections (first_name, email, company…) rely on them.
 */
class ProfileFormController extends Controller
{
    public const AUDIENCES = ['attendee', 'speaker', 'exhibitor', 'sponsor', 'organizer'];

    /** participation role each audience's approved submission becomes */
    protected const ROLES = [
        'attendee' => 'attendee',
        'speaker' => 'speaker',
        'exhibitor' => 'exhibitor',
        'sponsor' => 'sponsor',
        'organizer' => 'staff',
    ];

    // ── Builder ─────────────────────────────────────────────────────

    /** GET /events/{uuid}/profile-forms — the five services, provisioned on first read. */
    public function index(string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $rows = collect(self::AUDIENCES)->map(function (string $audience) use ($event) {
            $form = $this->ensureForm($event, $audience);

            return [
                'audience' => $audience,
                'id' => $form->uuid,
                'name' => $form->name,
                'status' => $form->status,
                'version' => (int) $form->version,
                'fields_count' => $form->fields->where('type', '!=', 'section_break')->count(),
                'submissions_count' => $form->submissions()->count(),
                'pending_count' => $form->submissions()->where('review_status', 'pending')->count(),
                'updated_at' => $form->updated_at?->toIso8601String(),
            ];
        });

        return response()->json(['data' => $rows->values()]);
    }

    /** GET /events/{uuid}/profile-forms/{audience} — full definition for the builder. */
    public function show(string $uuid, string $audience): JsonResponse
    {
        $form = $this->resolve($uuid, $audience)->load('fields.options');

        return response()->json(['data' => new FormResource($form)]);
    }

    /** PUT /events/{uuid}/profile-forms/{audience} — save the builder state. */
    public function update(string $uuid, string $audience, Request $request): JsonResponse
    {
        $form = $this->resolve($uuid, $audience);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            // Appearance of the shared/embedded public form. Validated key by
            // key: `settings` is organizer-authored JSON that ends up inlined
            // into a public page's styles, so nothing unrecognised gets stored.
            'design' => ['sometimes', 'nullable', 'array'],
            'design.background_type' => ['nullable', Rule::in(['color', 'image'])],
            'design.background_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'design.background_image_url' => ['nullable', 'url', 'max:2000'],
            'design.brand_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'design.card_style' => ['nullable', Rule::in(['solid', 'glass'])],
            'design.show_header' => ['nullable', 'boolean'],
            'fields' => ['required', 'array', 'max:80'],
            'fields.*.key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'fields.*.type' => ['required', 'string', Rule::in([
                'text', 'textarea', 'email', 'phone', 'number', 'date', 'select',
                'multiselect', 'checkbox', 'radio', 'link', 'file', 'rating',
                'section_break', 'recaptcha',
            ])],
            'fields.*.label' => ['nullable', 'string', 'max:180'],
            'fields.*.help_text' => ['nullable', 'string', 'max:500'],
            'fields.*.is_required' => ['sometimes', 'boolean'],
            'fields.*.is_unique' => ['sometimes', 'boolean'],
            'fields.*.is_pii' => ['sometimes', 'boolean'],
            'fields.*.validation' => ['sometimes', 'nullable', 'array'],
            'fields.*.meta' => ['sometimes', 'nullable', 'array'],
            'fields.*.options' => ['sometimes', 'array', 'max:100'],
            'fields.*.options.*.label' => ['required', 'string', 'max:180'],
            'fields.*.options.*.value' => ['nullable', 'string', 'max:180'],
        ]);

        $incoming = collect($data['fields'])->unique('key')->keyBy('key');

        DB::transaction(function () use ($form, $data, $incoming) {
            $existing = $form->fields()->with('options')->get()->keyBy('key');

            // Deletes first — but a seeded default field survives even if the
            // client dropped it; the builder only ever hides those.
            foreach ($existing as $key => $field) {
                if (! $incoming->has($key) && ! $field->is_default) {
                    $field->delete();
                }
            }

            $sort = 0;
            foreach ($incoming as $key => $f) {
                $attrs = [
                    'label' => $f['label'] ?? null,
                    'help_text' => $f['help_text'] ?? null,
                    'type' => $f['type'],
                    'is_required' => (bool) ($f['is_required'] ?? false),
                    'is_unique' => (bool) ($f['is_unique'] ?? false),
                    'is_pii' => (bool) ($f['is_pii'] ?? false),
                    'validation' => $f['validation'] ?? null,
                    'meta' => $f['meta'] ?? null,
                    'sort_order' => $sort++,
                ];

                if ($field = $existing->get($key)) {
                    // Type is frozen once answers exist — a text answer read
                    // back as a dropdown (or vice versa) corrupts submissions.
                    if ($field->type !== $f['type'] && FormFieldValue::where('field_id', $field->id)->exists()) {
                        unset($attrs['type']);
                    }
                    $field->update($attrs);
                } else {
                    $field = $form->fields()->create(['key' => $key] + $attrs);
                }

                $this->syncOptions($field, $f['options'] ?? null);
            }

            // Defaults the client omitted keep their data but leave the form.
            foreach ($existing as $key => $field) {
                if (! $incoming->has($key) && $field->is_default) {
                    $meta = $field->meta ?? [];
                    $meta['visible'] = false;
                    $field->update(['meta' => $meta, 'sort_order' => $sort++]);
                }
            }

            $attrs = ['updated_by' => request()->user()?->id];
            if (isset($data['name'])) {
                $attrs['name'] = $data['name'];
            }
            if (array_key_exists('design', $data)) {
                // Merge, so a partial design payload can't wipe the rest of
                // settings (or the keys a future panel adds).
                $attrs['settings'] = array_merge($form->settings ?? [], [
                    'design' => array_filter($data['design'] ?? [], fn ($v) => $v !== null),
                ]);
            }
            $form->update($attrs);
        });

        return response()->json(['data' => new FormResource($form->fresh()->load('fields.options'))]);
    }

    /** POST /events/{uuid}/profile-forms/{audience}/publish */
    public function publish(string $uuid, string $audience): JsonResponse
    {
        $form = $this->resolve($uuid, $audience);
        $form->update(['status' => 'published', 'version' => $form->version + 1]);

        return response()->json(['data' => new FormResource($form->load('fields.options'))]);
    }

    /**
     * DELETE /events/{uuid}/profile-forms/{audience} — reset to defaults.
     * Destroys the form AND its submissions (cascade), then re-provisions the
     * seeded draft. The client confirms loudly before calling this.
     */
    public function destroy(string $uuid, string $audience): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $this->assertAudience($audience);

        Form::where('event_id', $event->id)->where('key', "profile.{$audience}")->get()->each->forceDelete();
        $form = $this->ensureForm($event, $audience);

        return response()->json(['data' => new FormResource($form->load('fields.options'))]);
    }

    // ── Submissions ─────────────────────────────────────────────────

    /** GET /events/{uuid}/profile-forms/{audience}/submissions */
    public function submissions(string $uuid, string $audience, Request $request): JsonResponse
    {
        $form = $this->resolve($uuid, $audience);

        $query = $form->submissions()->latest('submitted_at');
        if ($request->filled('status')) {
            $query->where('review_status', $request->string('status'));
        }

        $page = $query->paginate(min((int) $request->input('per_page', 20), 100));

        return response()->json([
            'data' => collect($page->items())->map(fn (FormSubmission $s) => [
                'id' => $s->uuid,
                'source' => $s->source,
                'review_status' => $s->review_status,
                'submitter' => [
                    'name' => $s->meta['submitter_name'] ?? null,
                    'email' => $s->meta['submitter_email'] ?? null,
                ],
                'form_version' => (int) $s->form_version,
                'submitted_at' => $s->submitted_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'audience' => $audience,
                'form_name' => $form->name,
            ],
        ]);
    }

    /**
     * POST /events/{uuid}/profile-forms/{audience}/submissions/export
     * One row per submission, one column per field — the shape people expect to
     * paste into a CRM. Returned as JSON so the client keeps its bearer token
     * and turns it into a download (same pattern as the leads exports).
     */
    public function submissionsExport(string $uuid, string $audience, Request $request): JsonResponse
    {
        $form = $this->resolve($uuid, $audience);
        $form->load('fields.options');

        $query = $form->submissions()->orderBy('submitted_at');
        if ($request->filled('status')) {
            $query->where('review_status', $request->string('status'));
        }
        $submissions = $query->get();

        $fields = $form->fields->reject(fn ($f) => in_array($f->type, ['section_break', 'recaptcha'], true))->values();

        $values = FormFieldValue::whereIn('submission_id', $submissions->pluck('id'))
            ->get()
            ->groupBy('submission_id');

        $columns = array_merge(
            ['Submitted at', 'Source', 'Status', 'Submitter', 'Email'],
            $fields->map(fn ($f) => $f->label ?: $f->key)->all(),
        );

        $rows = $submissions->map(function (FormSubmission $s) use ($fields, $values) {
            $byField = ($values[$s->id] ?? collect())->keyBy('field_id');

            $answers = $fields->map(function ($field) use ($byField) {
                $raw = $byField->get($field->id)?->value;
                $value = is_string($raw) ? json_decode($raw, true) : $raw;

                $labels = $field->options->pluck('label', 'value');
                if ($labels->isNotEmpty()) {
                    $value = is_array($value)
                        ? collect($value)->map(fn ($v) => $labels->get($v, $v))->all()
                        : $labels->get($value, $value);
                }

                return is_array($value) ? implode('; ', $value) : (is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value);
            })->all();

            return array_merge([
                $s->submitted_at?->toDateTimeString() ?? '',
                $s->source,
                $s->review_status,
                $s->meta['submitter_name'] ?? '',
                $s->meta['submitter_email'] ?? '',
            ], $answers);
        });

        return response()->json(['data' => [
            'csv' => $this->toCsv($columns, $rows),
            'filename' => Str::slug($form->name.'-submissions-'.now()->format('Y-m-d')).'.csv',
            'count' => $submissions->count(),
        ]]);
    }

    /** GET /profile-submissions/{uuid} — answers resolved against field labels. */
    public function submissionShow(string $uuid): JsonResponse
    {
        $submission = FormSubmission::where('uuid', $uuid)->firstOrFail();
        $form = Form::with('fields.options')->findOrFail($submission->form_id);

        $values = FormFieldValue::where('submission_id', $submission->id)
            ->get()
            ->keyBy('field_id');

        $answers = $form->fields
            ->reject(fn ($f) => in_array($f->type, ['section_break', 'recaptcha']))
            ->map(function ($field) use ($values) {
                $raw = $values->get($field->id)?->value;
                $value = is_string($raw) ? json_decode($raw, true) : $raw;

                // Option values → their human labels.
                $labels = $field->options->pluck('label', 'value');
                if ($labels->isNotEmpty()) {
                    $value = is_array($value)
                        ? collect($value)->map(fn ($v) => $labels->get($v, $v))->values()->all()
                        : $labels->get($value, $value);
                }

                return [
                    'key' => $field->key,
                    'label' => $field->label ?: $field->key,
                    'type' => $field->type,
                    'value' => $value,
                ];
            })->values();

        return response()->json(['data' => [
            'id' => $submission->uuid,
            'source' => $submission->source,
            'review_status' => $submission->review_status,
            'submitter' => [
                'name' => $submission->meta['submitter_name'] ?? null,
                'email' => $submission->meta['submitter_email'] ?? null,
            ],
            'form_version' => (int) $submission->form_version,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'answers' => $answers,
        ]]);
    }

    /**
     * PATCH /profile-submissions/{uuid} — approve | reject.
     * Approval is the payoff: the submitter becomes a contact + participation
     * in the audience's role, with the submitted answers projected onto their
     * per-event profile.
     */
    public function submissionReview(string $uuid, Request $request): JsonResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'reject'])]]);

        $submission = FormSubmission::where('uuid', $uuid)->firstOrFail();
        $form = Form::with('fields')->findOrFail($submission->form_id);
        $audience = Str::after($form->key, 'profile.');

        if ($data['action'] === 'reject') {
            $submission->update(['review_status' => 'rejected']);

            return response()->json(['data' => ['id' => $submission->uuid, 'review_status' => 'rejected']]);
        }

        $result = DB::transaction(function () use ($submission, $form, $audience) {
            $values = $this->decodedValues($submission, $form);
            $email = $submission->meta['submitter_email'] ?? $this->firstEmail($form, $values);

            $participationUuid = null;
            if ($email && $form->event_id) {
                $participationUuid = $this->promote($form, $audience, $email, $values, $submission);
            }

            $submission->update(['review_status' => 'approved']);

            return ['participation' => $participationUuid];
        });

        return response()->json(['data' => [
            'id' => $submission->uuid,
            'review_status' => 'approved',
            'participation' => $result['participation'],
        ]]);
    }

    /** DELETE /profile-submissions/{uuid} */
    public function submissionDestroy(string $uuid): JsonResponse
    {
        FormSubmission::where('uuid', $uuid)->firstOrFail()->delete();

        return response()->json(['ok' => true]);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /**
     * RFC-4180 CSV with a UTF-8 BOM (without it Excel reads the file as the
     * local codepage and mangles non-ASCII names) and a spreadsheet-injection
     * guard: a value opening with = + - @ is prefixed so Excel treats it as
     * text rather than a formula.
     */
    protected function toCsv(array $columns, Collection $rows): string
    {
        $escape = function ($v) {
            $v = (string) $v;
            if ($v !== '' && str_contains('=+-@', $v[0])) {
                $v = "'".$v;
            }

            return '"'.str_replace('"', '""', $v).'"';
        };

        $lines = [implode(',', array_map($escape, $columns))];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map($escape, array_values($row)));
        }

        return "\u{FEFF}".implode("\r\n", $lines);
    }

    protected function assertAudience(string $audience): void
    {
        abort_unless(in_array($audience, self::AUDIENCES, true), 404);
    }

    protected function resolve(string $eventUuid, string $audience): Form
    {
        $this->assertAudience($audience);
        $event = Event::where('uuid', $eventUuid)->firstOrFail();

        return $this->ensureForm($event, $audience);
    }

    /** The audience's form — or a fresh draft seeded from config defaults. */
    protected function ensureForm(Event $event, string $audience): Form
    {
        $form = Form::with('fields')
            ->where('event_id', $event->id)
            ->where('key', "profile.{$audience}")
            ->latest('id')
            ->first();

        if ($form) {
            return $form;
        }

        $form = Form::create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'key' => "profile.{$audience}",
            'name' => Str::ucfirst($audience),
            'target_entity' => 'participation',
            'status' => 'draft',
            'version' => 1,
            'created_by' => request()->user()?->id,
        ]);

        $sort = 0;
        foreach (config("eventos.profile_defaults.{$audience}", []) as $f) {
            $field = $form->fields()->create([
                'key' => $f['key'],
                'label' => $f['label'] ?? null,
                'type' => $f['type'],
                'is_default' => true,
                'is_required' => $f['is_required'] ?? false,
                'is_unique' => $f['is_unique'] ?? false,
                'is_pii' => $f['is_pii'] ?? false,
                'meta' => $f['meta'] ?? null,
                'sort_order' => $sort++,
            ]);

            foreach ($f['options'] ?? [] as $i => $label) {
                $field->options()->create(['label' => $label, 'value' => $label, 'sort_order' => $i]);
            }
        }

        return $form->load('fields');
    }

    protected function syncOptions($field, ?array $options): void
    {
        if ($options === null) {
            return; // untouched — field types without options never send the key
        }

        $field->options()->delete();
        foreach ($options as $i => $opt) {
            $field->options()->create([
                'label' => $opt['label'],
                'value' => $opt['value'] ?? $opt['label'],
                'sort_order' => $i,
            ]);
        }
    }

    /** @return array<string,mixed> field key => decoded answer */
    protected function decodedValues(FormSubmission $submission, Form $form): array
    {
        $byField = FormFieldValue::where('submission_id', $submission->id)->get()->keyBy('field_id');

        $out = [];
        foreach ($form->fields as $field) {
            if (! $byField->has($field->id)) {
                continue;
            }
            $raw = $byField->get($field->id)->value;
            $out[$field->key] = is_string($raw) ? json_decode($raw, true) : $raw;
        }

        return $out;
    }

    protected function firstEmail(Form $form, array $values): ?string
    {
        foreach ($form->fields as $field) {
            if ($field->type === 'email' && filter_var($values[$field->key] ?? null, FILTER_VALIDATE_EMAIL)) {
                return $values[$field->key];
            }
        }

        return null;
    }

    /** Approved submitter → contact + participation with the projected profile. */
    protected function promote(Form $form, string $audience, string $email, array $values, FormSubmission $submission): string
    {
        $first = $values['first_name'] ?? ($values['contact_name'] ?? null);
        $last = $values['last_name'] ?? null;

        $contact = Contact::firstOrCreate(
            ['email' => $email],
            ['first_name' => $first, 'last_name' => $last],
        );

        $role = self::ROLES[$audience] ?? 'attendee';

        $participation = Participation::firstOrNew(
            ['event_id' => $form->event_id, 'contact_id' => $contact->id, 'role' => $role],
        );
        if (! $participation->exists) {
            $participation->forceFill([
                'role' => $role,
                'status' => 'registered',
                'registration_submission_id' => $submission->id,
            ])->save();
        }

        $projection = collect($values)->except(['first_name', 'last_name'])->all();
        $participation->projectDynamic($projection)->save();
        $contact->projectDynamic($projection)->save();

        $submission->update(['owner_type' => 'participation', 'owner_id' => $participation->id]);

        return $participation->uuid;
    }
}
