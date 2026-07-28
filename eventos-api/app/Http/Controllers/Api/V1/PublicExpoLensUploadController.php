<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ExpoLens\ProcessExpoLensPhotoJob;
use App\Models\ExpoLensPhoto;
use App\Models\ExpoLensUploadLink;
use App\Models\File;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Account-free photo drop for hired photographers (no auth, no tenant header).
 *
 * The token in the URL is the only credential, so the link is resolved on the
 * BYPASSRLS admin connection — mirroring PublicSiteController — and the tenant
 * is then pinned from the link itself before anything is written. Every write
 * after that point runs under the correct organization for both the app-level
 * scope and the RLS GUC.
 */
class PublicExpoLensUploadController extends Controller
{
    public function __construct(protected TenantContext $tenant) {}

    /** Link preview: what the photographer sees before dropping files in. */
    public function show(string $token): JsonResponse
    {
        $link = $this->resolve($token);

        return response()->json(['data' => [
            'label' => $link->label,
            'album' => $link->album,
            'event_name' => $link->event?->name,
            'remaining' => $link->remaining(),
            'expires_at' => $link->expires_at?->toIso8601String(),
            'auto_approve' => $link->auto_approve,
        ]]);
    }

    public function store(string $token, Request $request): JsonResponse
    {
        $link = $this->resolve($token);

        $request->validate([
            'file' => ['required', 'file', 'max:25600', 'mimes:png,jpg,jpeg,webp'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $this->activateTenant((int) $link->organization_id);

        $upload = $request->file('file');
        $ext = $upload->getClientOriginalExtension() ?: ($upload->extension() ?: 'jpg');

        $path = Storage::disk('s3')->putFileAs(
            "expolens/{$link->organization_id}",
            $upload,
            Str::uuid().".{$ext}",
        );

        $file = File::create([
            'organization_id' => $link->organization_id,
            'collection' => 'expolens',
            'disk' => 's3',
            'path' => $path,
            'filename' => $this->safeFilename($upload->getClientOriginalName(), $ext),
            'mime_type' => $upload->getMimeType(),
            'size_bytes' => $upload->getSize(),
            'visibility' => 'public',
            'uploaded_by' => null,
        ]);

        $photo = ExpoLensPhoto::create([
            'event_id' => $link->event_id,
            'organization_id' => $link->organization_id,
            'file_id' => $file->id,
            'url' => Storage::disk('s3')->url($path),
            'caption' => $request->input('caption'),
            'album' => $link->album,
            'processing_status' => 'pending',
            'moderation_status' => $link->auto_approve ? 'approved' : 'pending',
            'uploaded_by' => null,
            'photographer_meta' => [
                'name' => $link->label,
                'filename' => $file->filename,
                'via_link' => $link->uuid,
            ],
        ]);

        // Counter guarded by the same usability check the request passed, so a
        // burst of parallel uploads can't run far past max_uploads.
        DB::table('expolens_upload_links')
            ->where('id', $link->id)
            ->update([
                'uploads_count' => DB::raw('uploads_count + 1'),
                'last_used_at' => now(),
                'updated_at' => now(),
            ]);

        ProcessExpoLensPhotoJob::dispatch((int) $link->organization_id, $photo->id);

        return response()->json(['data' => [
            'id' => $photo->uuid,
            'url' => $photo->url,
            'filename' => $file->filename,
        ]], 201);
    }

    /**
     * Resolve a usable link, or abort with a reason the photographer can read.
     * Runs on the BYPASSRLS connection because no tenant exists yet.
     */
    private function resolve(string $token): ExpoLensUploadLink
    {
        $link = ExpoLensUploadLink::on('pgsql_admin')
            ->withoutGlobalScope('organization')
            ->with('event')
            ->where('token', $token)
            ->first();

        abort_unless($link, 404, 'This upload link is not valid.');
        abort_if($link->isRevoked(), 403, 'This upload link has been revoked.');
        abort_if($link->isExpired(), 403, 'This upload link has expired.');
        abort_if($link->isExhausted(), 403, 'This upload link has reached its upload limit.');

        return $link;
    }

    private function activateTenant(int $organizationId): void
    {
        $this->tenant->set($organizationId);
        DB::statement("set app.current_organization = '{$organizationId}'");
    }

    /** Mirrors FileUploadController: the stored path is a UUID, this is display only. */
    private function safeFilename(string $original, string $ext): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $original) ?? '';
        $stem = basename(str_replace('\\', '/', $clean));
        $stem = preg_replace('/(\.[A-Za-z0-9]{1,8})+$/', '', $stem) ?? '';
        $stem = mb_substr(trim($stem), 0, 120);

        if ($stem === '') {
            $stem = 'photo';
        }

        return $ext !== '' ? "{$stem}.{$ext}" : $stem;
    }
}
