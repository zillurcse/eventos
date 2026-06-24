<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Services\Email\EmailDispatcher;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmailTemplateController extends Controller
{
    /** RLS scopes this to the org's own + shared (NULL-org) templates. */
    public function index(): AnonymousResourceCollection
    {
        return EmailTemplateResource::collection(EmailTemplate::latest('id')->get());
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json(['data' => new EmailTemplateResource(
            EmailTemplate::where('uuid', $uuid)->firstOrFail()
        )]);
    }

    public function store(Request $request, TenantContext $tenant, EmailDispatcher $dispatcher): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'key' => ['nullable', 'string', 'max:80'],
            'subject' => ['nullable', 'string', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:180'],
            'from_email' => ['nullable', 'email', 'max:180'],
            'blocks' => ['array'],
            'blocks.*.type' => ['required_with:blocks', 'string'],
        ]);

        $template = EmailTemplate::create([
            'organization_id' => $tenant->id(),
            'name' => $data['name'],
            'key' => $data['key'] ?? 'custom',
            'subject' => $data['subject'] ?? null,
            'from_name' => $data['from_name'] ?? null,
            'from_email' => $data['from_email'] ?? null,
            // input() not validated() — keep the full block payload (content/etc.)
            'design' => ['blocks' => $request->input('blocks', [])],
            'status' => 'draft',
            'version' => 1,
        ]);

        $dispatcher->compile($template);

        return response()->json(['data' => new EmailTemplateResource($template->fresh())], 201);
    }

    public function preview(string $uuid, Request $request, EmailDispatcher $dispatcher): JsonResponse
    {
        $template = EmailTemplate::where('uuid', $uuid)->firstOrFail();
        $merge = $request->input('merge', []);

        return response()->json(['html' => $dispatcher->preview($template, $merge)]);
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
}
