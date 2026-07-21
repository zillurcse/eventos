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
 * the tenant (organizer) or exhibitor GUC, so the `files` row satisfies RLS.
 */
class FileUploadController extends Controller
{
    public function __construct(protected TenantContext $tenant) {}

    public function store(Request $request): JsonResponse
    {
        // The `document` and `session_doc` collections carry presentation decks /
        // handouts, so they accept common office file types (and images) up to
        // 20 MB. The `feed` collection additionally accepts video for attendee
        // feed posts (≤ 80 MB); `chat` mirrors it plus office docs for message
        // attachments. Every other collection is image-only (≤ 5 MB).
        $collectionInput = $request->input('collection');
        // A survey's `file` question takes whatever the organizer asked for —
        // a CV, a photo, a signed form — so it follows the document rules.
        $isDocument = in_array($collectionInput, ['document', 'session_doc', 'survey_response'], true);
        // Contest entries carry the same attendee-shot photo/video as feed posts.
        $isFeed = in_array($collectionInput, ['feed', 'contest_entry'], true);
        $isChat = $collectionInput === 'chat';

        $data = $request->validate([
            'file' => match (true) {
                $isFeed => ['required', 'file', 'max:81920', 'mimes:png,jpg,jpeg,webp,gif,mp4,webm,mov,pdf'],
                $isChat => ['required', 'file', 'max:81920', 'mimes:png,jpg,jpeg,webp,gif,mp4,webm,mov,pdf,doc,docx,xls,xlsx,csv,ppt,pptx,txt'],
                $isDocument => ['required', 'file', 'max:20480', 'mimes:pdf,ppt,pptx,doc,docx,xls,xlsx,csv,txt,key,png,jpg,jpeg,webp'],
                // Explicit raster list — NOT the `image` rule, which admits SVG
                // (inline-script XSS) and is served public under tenant-trusted
                // origins. Mirrors the other branches' allow-lists.
                default => ['required', 'file', 'mimes:png,jpg,jpeg,webp,gif', 'max:5120'],
            },
            'collection' => ['nullable', Rule::in([
                'cover', 'logo', 'avatar', 'document', 'banner', 'banners', 'email_header', 'feed', 'email',
                'ad_image', 'lounge', 'breakout_room_poster', 'session_doc', 'session_icon', 'session_logo',
                'exhibitor_logo', 'exhibitor_spotlight', 'ctas', 'chat', 'contest_banner', 'contest_entry',
                'survey_response',
            ])],
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
            'filename' => $this->safeFilename($upload->getClientOriginalName(), $ext),
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
            'mime_type' => $file->mime_type,
            'filename' => $file->filename,
        ]], 201);
    }

    /**
     * A safe *display* filename. The stored path is already a UUID (no traversal),
     * so this is purely the human-readable name we persist and echo back. We strip
     * control chars / NUL, drop any path the client embedded, collapse trailing
     * extension runs so a double-extension ("invoice.pdf.svg") can't masquerade,
     * then re-append the single extension the upload actually validated as.
     */
    private function safeFilename(string $original, string $ext): string
    {
        // Strip NUL + control chars first (a NUL could truncate downstream), then
        // reduce to a bare basename so "../../x" and "..\\x" carry no path.
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $original) ?? '';
        $stem = basename(str_replace('\\', '/', $clean));

        // Drop every trailing ".<ext>" run, then re-attach one trustworthy ext.
        $stem = preg_replace('/(\.[A-Za-z0-9]{1,8})+$/', '', $stem) ?? '';
        $stem = mb_substr(trim($stem), 0, 120);
        if ($stem === '') {
            $stem = 'file';
        }

        return $ext !== '' ? "{$stem}.{$ext}" : $stem;
    }
}
