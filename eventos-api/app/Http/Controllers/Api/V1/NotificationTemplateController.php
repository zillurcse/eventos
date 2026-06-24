<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = NotificationTemplate::latest('id')->get(['id', 'key', 'channel', 'locale', 'subject']);

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request, TenantContext $tenant): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:120'],
            'channel' => ['required', 'in:email,push,sms,in_app'],
            'locale' => ['nullable', 'string', 'max:10'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);

        $template = NotificationTemplate::create([
            'organization_id' => $tenant->id(),
            'key' => $data['key'],
            'channel' => $data['channel'],
            'locale' => $data['locale'] ?? null,
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'] ?? null,
        ]);

        return response()->json(['data' => $template->only('id', 'key', 'channel')], 201);
    }
}
