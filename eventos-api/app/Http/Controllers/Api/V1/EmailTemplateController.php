<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmailTemplateResource;
use App\Models\Event;
use App\Models\EmailTemplate;
use App\Services\Email\EmailDispatcher;
use App\Services\Email\EventTemplateSeeder;
use App\Services\Email\MergeVariables;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class EmailTemplateController extends Controller
{
    /** RLS scopes this to the org's own + shared (NULL-org) templates; ?event filters to one event. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = EmailTemplate::latest('id');

        if ($request->filled('event')) {
            $event = Event::where('uuid', $request->string('event'))->firstOrFail();
            $query->where('event_id', $event->id);
        }

        return EmailTemplateResource::collection($query->get());
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json(['data' => new EmailTemplateResource(
            EmailTemplate::where('uuid', $uuid)->firstOrFail()
        )]);
    }

    public function store(Request $request, TenantContext $tenant, EmailDispatcher $dispatcher): JsonResponse
    {
        $data = $this->validateTemplate($request);

        $template = EmailTemplate::create([
            'organization_id' => $tenant->id(),
            'event_id' => $this->resolveEventId($data['event'] ?? null),
            'name' => $data['name'],
            'key' => $data['key'] ?? 'custom',
            'subject' => $data['subject'] ?? null,
            'from_name' => $data['from_name'] ?? null,
            'from_email' => $data['from_email'] ?? null,
            'reply_to' => $data['reply_to'] ?? null,
            // input() not validated() — keep the full nested block/settings payload.
            'design' => $this->design($request),
            'status' => 'draft',
            'version' => 1,
        ]);

        $dispatcher->compile($template);

        return response()->json(['data' => new EmailTemplateResource($template->fresh())], 201);
    }

    public function update(string $uuid, Request $request, EmailDispatcher $dispatcher): JsonResponse
    {
        $template = EmailTemplate::where('uuid', $uuid)->firstOrFail();
        $data = $this->validateTemplate($request);

        $template->update([
            'name' => $data['name'],
            'key' => $data['key'] ?? $template->key,
            'subject' => $data['subject'] ?? null,
            'from_name' => $data['from_name'] ?? null,
            'from_email' => $data['from_email'] ?? null,
            'reply_to' => $data['reply_to'] ?? null,
            'design' => $this->design($request),
            'status' => $data['status'] ?? $template->status,
            'version' => $template->version + 1,
        ]);

        $dispatcher->compile($template);

        return response()->json(['data' => new EmailTemplateResource($template->fresh())]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        EmailTemplate::where('uuid', $uuid)->firstOrFail()->delete();

        return response()->json(['status' => 'success']);
    }

    public function duplicate(string $uuid, TenantContext $tenant, EmailDispatcher $dispatcher): JsonResponse
    {
        $source = EmailTemplate::where('uuid', $uuid)->firstOrFail();

        $copy = $source->replicate(['uuid', 'compiled_html', 'version']);
        $copy->uuid = (string) Str::uuid();
        $copy->name = Str::limit($source->name, 170, '').' (Copy)';
        $copy->status = 'draft';
        $copy->version = 1;
        $copy->organization_id = $tenant->id();
        $copy->save();

        $dispatcher->compile($copy);

        return response()->json(['data' => new EmailTemplateResource($copy->fresh())], 201);
    }

    public function preview(string $uuid, Request $request, EmailDispatcher $dispatcher): JsonResponse
    {
        $template = EmailTemplate::where('uuid', $uuid)->firstOrFail();

        return response()->json(['html' => $dispatcher->preview($template, $request->input('merge', []))]);
    }

    /** Preview unsaved canvas state without persisting (live editor preview). */
    public function previewDraft(Request $request, EmailDispatcher $dispatcher): JsonResponse
    {
        $template = new EmailTemplate([
            'subject' => $request->input('subject'),
            'design' => $this->design($request),
        ]);

        return response()->json(['html' => $dispatcher->preview($template, $request->input('merge', []))]);
    }

    public function sendTest(string $uuid, Request $request, EmailDispatcher $dispatcher): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'email'],
            'merge' => ['array'],
        ]);

        $template = EmailTemplate::where('uuid', $uuid)->firstOrFail();
        $send = $dispatcher->send($template, $data['to'], $data['merge'] ?? []);

        return response()->json([
            'email_send_id' => $send->uuid,
            'to' => $send->to_email,
            'subject' => $send->subject,
            'status' => $send->status,
        ], 202);
    }

    /** Seed the 36 default system templates for an event (idempotent). */
    public function seed(Request $request, TenantContext $tenant, EventTemplateSeeder $seeder): JsonResponse
    {
        $data = $request->validate(['event' => ['required', 'string']]);
        $event = Event::where('uuid', $data['event'])->firstOrFail();

        $seeder->seedForEvent($event, $tenant->id());

        $templates = EmailTemplate::where('event_id', $event->id)->latest('id')->get();

        return response()->json([
            'seeded' => $templates->count(),
            'data'   => EmailTemplateResource::collection($templates),
        ]);
    }

    /** The dynamic merge-variable catalogue surfaced to the builder's variable picker. */
    public function variables(MergeVariables $variables): JsonResponse
    {
        return response()->json(['data' => $variables->catalogue()]);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'key' => ['nullable', 'string', 'max:80'],
            'subject' => ['nullable', 'string', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:180'],
            'from_email' => ['nullable', 'email', 'max:180'],
            'reply_to' => ['nullable', 'email', 'max:180'],
            'status' => ['nullable', 'in:draft,published'],
            'event' => ['nullable', 'string'],
            'blocks' => ['array'],
            'blocks.*.type' => ['required_with:blocks', 'string'],
            'settings' => ['array'],
        ]);
    }

    /** Full design payload — blocks + global canvas settings (nested, so via input()). */
    private function design(Request $request): array
    {
        return [
            'blocks' => $request->input('blocks', []),
            'settings' => $request->input('settings', []),
        ];
    }

    private function resolveEventId(?string $uuid): ?int
    {
        if (! $uuid) {
            return null;
        }

        return Event::where('uuid', $uuid)->firstOrFail()->id;
    }
}
