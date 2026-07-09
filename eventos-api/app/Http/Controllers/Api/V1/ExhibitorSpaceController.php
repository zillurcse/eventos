<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExhibitorProductResource;
use App\Http\Resources\ExhibitorResource;
use App\Models\Booth;
use App\Models\Exhibitor;
use App\Models\EventSetting;
use App\Services\DomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Exhibitor-admin self-service space (architecture §6.3). The active exhibitor is
 * resolved by the ResolveExhibitorAdmin middleware (tenant GUC = the exhibitor's org).
 */
class ExhibitorSpaceController extends Controller
{
    private const WITH = ['package', 'members.contact', 'products', 'logoFile'];

    /** Contact/booth fields the exhibitor may edit; stored flat in profile_data. */
    private const PROFILE_KEYS = ['stall_no', 'phone_code', 'phone', 'website_url'];

    public function show(Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::with(self::WITH)->findOrFail($request->attributes->get('exhibitor_id'));

        $data = (new ExhibitorResource($exhibitor))->toArray($request);
        $data['public_url'] = $this->publicUrl($exhibitor);

        return response()->json(['data' => $data]);
    }

    /** Exhibitor edits its own public profile (name/description/logo + contact fields). */
    public function update(Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::findOrFail($request->attributes->get('exhibitor_id'));

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Contact/booth fields live in profile_data (spread flat by the resource).
            'stall_no' => ['sometimes', 'nullable', 'string', 'max:60'],
            'phone_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'website_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            // RLS scopes `files` to this org, so exists() also enforces ownership.
            'logo_file_id' => ['sometimes', 'nullable', 'integer', Rule::exists('files', 'id')],
        ]);

        // Merge the profile_data fields so unrelated keys (address, social, …) survive.
        $profile = array_intersect_key($data, array_flip(self::PROFILE_KEYS));
        $cols = array_diff_key($data, array_flip(self::PROFILE_KEYS));
        if ($profile) {
            $cols['profile_data'] = array_merge($exhibitor->profile_data ?? [], $profile);
        }

        $exhibitor->update($cols);

        $fresh = $exhibitor->fresh(self::WITH);
        $out = (new ExhibitorResource($fresh))->toArray($request);
        $out['public_url'] = $this->publicUrl($fresh);

        return response()->json(['data' => $out]);
    }

    /** Public company page on the event's live site (subdomain or custom domain). */
    protected function publicUrl(Exhibitor $exhibitor): ?string
    {
        $domain = EventSetting::where('event_id', $exhibitor->event_id)->first()?->domain ?? [];

        $base = null;
        if (($domain['custom_domain'] ?? null) && ($domain['status'] ?? null) === DomainService::STATUS_ACTIVE) {
            $base = 'https://'.$domain['custom_domain'];
        } elseif ($domain['subdomain'] ?? null) {
            $base = 'https://'.$domain['subdomain'].'.'.app(DomainService::class)->apex();
        }

        return $base ? $base.'/exhibitor/'.$exhibitor->uuid : null;
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::findOrFail($request->attributes->get('exhibitor_id'));

        $product = $exhibitor->products()->create(ExhibitorProductController::validated($request));

        return response()->json(['data' => new ExhibitorProductResource($product)], 201);
    }

    // ── Exhibitor booth details ──────────────────────────────

    public function showBooth(Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::findOrFail($request->attributes->get('exhibitor_id'));
        abort_unless($exhibitor->type === 'exhibitor', 422, 'Only exhibitors have a booth.');

        $booth = Booth::where('exhibitor_id', $exhibitor->id)->first();

        return response()->json(['data' => $booth ? $this->boothArray($booth) : null]);
    }

    public function updateBooth(Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::findOrFail($request->attributes->get('exhibitor_id'));
        abort_unless($exhibitor->type === 'exhibitor', 422, 'Only exhibitors have a booth.');

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:60'],
            'type' => ['nullable', Rule::in(['physical', 'virtual'])],
            'resources' => ['nullable', 'array'],
        ]);

        $booth = Booth::updateOrCreate(
            ['exhibitor_id' => $exhibitor->id],
            [
                'event_id' => $exhibitor->event_id,
                'organization_id' => $exhibitor->organization_id,
                'code' => $data['code'] ?? null,
                'type' => $data['type'] ?? 'physical',
                'resources' => $data['resources'] ?? null,
            ],
        );

        return response()->json(['data' => $this->boothArray($booth)]);
    }

    protected function boothArray(Booth $b): array
    {
        return [
            'id' => $b->id,
            'code' => $b->code,
            'type' => $b->type,
            'resources' => $b->resources ?? [],
        ];
    }
}
