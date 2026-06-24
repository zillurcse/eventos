<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Image uploads → MinIO (the `s3` disk). The bucket is bootstrapped with public
 * download, so the returned URL is directly loadable in the browser. Runs under
 * the tenant (organizer) or partner GUC, so the `files` row satisfies RLS.
 */
class FileUploadController extends Controller
{
    public function __construct(protected TenantContext $tenant) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'image', 'max:5120'],   // ≤ 5 MB
            'collection' => ['nullable', Rule::in(['cover', 'logo', 'avatar', 'document', 'banner', 'email_header'])],
        ]);

        $upload = $request->file('file');
        $collection = $data['collection'] ?? 'cover';
        $orgId = $this->tenant->has() ? $this->tenant->id() : null;
        $ext = $upload->getClientOriginalExtension() ?: ($upload->extension() ?: 'bin');

        $path = Storage::disk('s3')->putFileAs(
            "{$collection}/".($orgId ?? 'platform'),
            $upload,
            Str::uuid().".{$ext}",
        );

        $file = new File([
            'organization_id' => $orgId,
            'collection' => $collection,
            'disk' => 's3',
            'path' => $path,
            'filename' => $upload->getClientOriginalName(),
            'mime_type' => $upload->getMimeType(),
            'size_bytes' => $upload->getSize(),
            'visibility' => 'public',
            'uploaded_by' => $request->user()->id,
        ]);
        $file->save();

        return response()->json(['data' => [
            'id' => $file->id,
            'uuid' => $file->uuid,
            'url' => Storage::disk('s3')->url($path),
        ]], 201);
    }
}
