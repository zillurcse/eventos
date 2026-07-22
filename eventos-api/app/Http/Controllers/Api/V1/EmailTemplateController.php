<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateVersion;
use App\Models\Event;
use App\Models\File;
use App\Services\Email\DesignNormalizer;
use App\Services\Email\EmailDispatcher;
use App\Services\Email\EventTemplateSeeder;
use App\Services\Email\MergeVariables;
use App\Services\Email\TemplateVersions;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmailTemplateController extends Controller
{
    public function __construct(
        protected DesignNormalizer $normalizer,
        protected TemplateVersions $versions,
    ) {}

    /** RLS scopes this to the org's own + shared (NULL-org) templates; ?event filters to one event. */
    public function index(Request $request): AnonymousResourceCollection
    {
        // compiled_html is a large column and the list only needs to know a
        // template *has* one — the gallery fetches each card's preview lazily.
        $query = EmailTemplate::query()
            ->select(['id', 'uuid', 'organization_id', 'event_id', 'key', 'category', 'name',
                'subject', 'preheader', 'from_name', 'from_email', 'reply_to', 'design',
                'status', 'version', 'updated_at'])
            ->selectRaw('compiled_html IS NOT NULL AS has_compiled')
            ->latest('id');

        if ($request->filled('event')) {
            $event = Event::where('uuid', $request->string('event'))->firstOrFail();
            $query->where('event_id', $event->id);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        return EmailTemplateResource::collection($query->get());
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json(['data' => new EmailTemplateResource($this->find($uuid))]);
    }

    public function store(Request $request, TenantContext $tenant, EmailDispatcher $dispatcher): JsonResponse
    {
        $data = $this->validateTemplate($request);

        $template = EmailTemplate::create([
            'organization_id' => $tenant->id(),
            'event_id' => $this->resolveEventId($data['event'] ?? null),
            'name' => $data['name'],
            'key' => $data['key'] ?? 'custom',
            'category' => $data['category'] ?? 'custom',
            'subject' => $data['subject'] ?? null,
            'preheader' => $data['preheader'] ?? null,
            'from_name' => $data['from_name'] ?? null,
            'from_email' => $data['from_email'] ?? null,
            'reply_to' => $data['reply_to'] ?? null,
            'design' => $this->design($request),
            'status' => 'draft',
            'version' => 1,
            'created_by' => $request->user()?->id,
        ]);

        $dispatcher->compile($template);
        $this->versions->snapshot($template, $request->user()?->id);

        return response()->json(['data' => new EmailTemplateResource($template->fresh())], 201);
    }

    public function update(string $uuid, Request $request, EmailDispatcher $dispatcher): JsonResponse
    {
        $template = $this->find($uuid);
        $data = $this->validateTemplate($request);

        // Snapshot the outgoing state first, so history records what was replaced.
        $this->versions->snapshot($template, $request->user()?->id);

        $template->update([
            'name' => $data['name'],
            'key' => $data['key'] ?? $template->key,
            'category' => $data['category'] ?? $template->category,
            'subject' => $data['subject'] ?? null,
            'preheader' => $data['preheader'] ?? null,
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
        $this->find($uuid)->delete();

        return response()->json(['status' => 'success']);
    }

    public function duplicate(string $uuid, TenantContext $tenant, EmailDispatcher $dispatcher): JsonResponse
    {
        $source = $this->find($uuid);

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
        $template = $this->find($uuid);

        return response()->json(['html' => $dispatcher->preview($template, $request->input('merge', []))]);
    }

    /** Preview unsaved canvas state without persisting (live editor preview). */
    public function previewDraft(Request $request, EmailDispatcher $dispatcher): JsonResponse
    {
        $template = new EmailTemplate([
            'subject' => $request->input('subject'),
            'preheader' => $request->input('preheader'),
            'design' => $this->design($request),
        ]);

        return response()->json(['html' => $dispatcher->preview($template, $request->input('merge', []))]);
    }

    /**
     * The cached compiled HTML, for the gallery's lazily-loaded card previews.
     * Compiles on demand for templates saved before compilation was cached.
     */
    public function html(string $uuid, EmailDispatcher $dispatcher): JsonResponse
    {
        $template = $this->find($uuid);

        return response()->json([
            'html' => $template->compiled_html ?: $dispatcher->compile($template),
        ]);
    }

    /** Force a recompile — used after a restore, or to refresh a stale cache. */
    public function compile(string $uuid, EmailDispatcher $dispatcher): JsonResponse
    {
        return response()->json(['html' => $dispatcher->compile($this->find($uuid))]);
    }

    public function sendTest(string $uuid, Request $request, EmailDispatcher $dispatcher): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'email'],
            'merge' => ['array'],
        ]);

        $template = $this->find($uuid);
        $send = $dispatcher->send($template, $data['to'], $data['merge'] ?? []);

        return response()->json([
            'email_send_id' => $send->uuid,
            'to' => $send->to_email,
            'subject' => $send->subject,
            'status' => $send->status,
        ], 202);
    }

    // ── version history ─────────────────────────────────────────────────────

    public function versions(string $uuid): JsonResponse
    {
        $template = $this->find($uuid);

        return response()->json([
            'data' => $this->versions->history($template)->map(fn (EmailTemplateVersion $v) => [
                'version' => $v->version,
                'name' => $v->name,
                'subject' => $v->subject,
                'blocks' => count($v->design['blocks'] ?? []),
                'author' => $v->author?->name,
                'created_at' => optional($v->created_at)->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Roll the template back to a past version. The current state is snapshotted
     * first, so a restore is itself undoable.
     */
    public function restoreVersion(string $uuid, int $version, Request $request, EmailDispatcher $dispatcher): JsonResponse
    {
        $template = $this->find($uuid);

        $snapshot = EmailTemplateVersion::where('template_id', $template->id)
            ->where('version', $version)
            ->firstOrFail();

        // Forced: a restore must always be undoable, even if the editor
        // autosaved seconds ago and the coalescing window would skip it.
        $this->versions->snapshot($template, $request->user()?->id, force: true);
        $this->versions->restore($template, $snapshot);
        $dispatcher->compile($template);

        return response()->json(['data' => new EmailTemplateResource($template->fresh())]);
    }

    // ── reusable images ─────────────────────────────────────────────────────

    /**
     * The tenant's previously uploaded email imagery, for the editor's asset
     * picker. Reads the existing `files` table (RLS-scoped) rather than a
     * parallel table — uploads already land there via FileUploadController.
     */
    public function assets(Request $request): JsonResponse
    {
        $files = File::query()
            ->whereIn('collection', ['email', 'email_header', 'logo', 'cover', 'banner'])
            ->where('mime_type', 'like', 'image/%')
            ->latest('id')
            ->limit(min(100, max(1, (int) $request->integer('limit', 60))))
            ->get(['id', 'uuid', 'disk', 'path', 'filename', 'collection', 'created_at']);

        return response()->json([
            'data' => $files->map(fn (File $file) => [
                'id' => $file->uuid,
                'url' => Storage::disk($file->disk ?: 's3')->url($file->path),
                'filename' => $file->filename,
                'collection' => $file->collection,
                'created_at' => optional($file->created_at)->toIso8601String(),
            ]),
        ]);
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
            'data' => EmailTemplateResource::collection($templates),
        ]);
    }

    /** The dynamic merge-variable catalogue surfaced to the builder's variable picker. */
    public function variables(MergeVariables $variables): JsonResponse
    {
        return response()->json(['data' => $variables->catalogue()]);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function find(string $uuid): EmailTemplate
    {
        return EmailTemplate::where('uuid', $uuid)->firstOrFail();
    }

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'key' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', Rule::in(EmailTemplate::CATEGORIES)],
            'subject' => ['nullable', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:180'],
            'from_email' => ['nullable', 'email', 'max:180'],
            'reply_to' => ['nullable', 'email', 'max:180'],
            'status' => ['nullable', 'in:draft,published'],
            'event' => ['nullable', 'string'],
            'blocks' => ['array', 'max:400'],
            'settings' => ['array'],
        ]);
    }

    /**
     * Full design payload — blocks + global canvas settings. The tree is
     * recursive and free-form, so it is normalized (types, depth, size, HTML
     * sanitized) rather than described by validation rules.
     */
    private function design(Request $request): array
    {
        return $this->normalizer->normalize([
            'blocks' => $request->input('blocks', []),
            'settings' => $request->input('settings', []),
        ]);
    }

    private function resolveEventId(?string $uuid): ?int
    {
        if (! $uuid) {
            return null;
        }

        return Event::where('uuid', $uuid)->firstOrFail()->id;
    }
}
