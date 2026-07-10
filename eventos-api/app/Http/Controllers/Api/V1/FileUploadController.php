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
        // The `document` collection carries presentation decks / handouts, so it
        // accepts common office file types (and images) up to 20 MB. The `feed`
        // collection additionally accepts video for attendee feed posts (≤ 80 MB);
        // `chat` mirrors it plus office docs for message attachments.
        // Every other collection is image-only (≤ 5 MB).
        $collectionInput = $request->input('collection');
        $isDocument = $collectionInput === 'document';
        $isFeed = $collectionInput === 'feed';
        $isChat = $collectionInput === 'chat';

        $data = $request->validate([
            'file' => match (true) {
                $isFeed => ['required', 'file', 'max:81920', 'mimes:png,jpg,jpeg,webp,gif,mp4,webm,mov,pdf'],
                $isChat => ['required', 'file', 'max:81920', 'mimes:png,jpg,jpeg,webp,gif,mp4,webm,mov,pdf,doc,docx,xls,xlsx,csv,ppt,pptx,txt'],
                $isDocument => ['required', 'file', 'max:20480', 'mimes:pdf,ppt,pptx,doc,docx,xls,xlsx,csv,txt,key,png,jpg,jpeg,webp'],
                default => ['required', 'file', 'image', 'max:5120'],
            },
            'collection' => ['nullable', Rule::in([
                'cover', 'logo', 'avatar', 'document', 'banner', 'banners', 'email_header', 'feed', 'email',
                'ad_image', 'lounge', 'breakout_room_poster', 'session_doc', 'session_icon', 'session_logo',
                'exhibitor_logo', 'exhibitor_spotlight', 'ctas', 'chat', 'contest_banner',
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
            'mime_type' => $file->mime_type,
            'filename' => $file->filename,
        ]], 201);
    }
}
