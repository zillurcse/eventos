<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartnerProductResource;
use App\Http\Resources\PartnerResource;
use App\Models\Booth;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Partner-admin self-service space (architecture §6.3). The active partner is
 * resolved by the ResolvePartnerAdmin middleware (tenant GUC = the partner's org).
 */
class PartnerSpaceController extends Controller
{
    private const WITH = ['package', 'members.contact', 'products', 'logoFile'];

    public function show(Request $request): JsonResponse
    {
        $partner = Partner::with(self::WITH)->findOrFail($request->attributes->get('partner_id'));

        return response()->json(['data' => new PartnerResource($partner)]);
    }

    /** Partner edits its own public profile (name/description/website/logo/profile_data). */
    public function update(Request $request): JsonResponse
    {
        $partner = Partner::findOrFail($request->attributes->get('partner_id'));

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'profile_data' => ['sometimes', 'array'],
            // RLS scopes `files` to this org, so exists() also enforces ownership.
            'logo_file_id' => ['sometimes', 'nullable', 'integer', Rule::exists('files', 'id')],
        ]);

        $partner->update($data);

        return response()->json(['data' => new PartnerResource($partner->fresh(self::WITH))]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $partner = Partner::findOrFail($request->attributes->get('partner_id'));

        $product = $partner->products()->create(PartnerProductController::validated($request));

        return response()->json(['data' => new PartnerProductResource($product)], 201);
    }

    // ── Exhibitor booth details ──────────────────────────────

    public function showBooth(Request $request): JsonResponse
    {
        $partner = Partner::findOrFail($request->attributes->get('partner_id'));
        abort_unless($partner->type === 'exhibitor', 422, 'Only exhibitors have a booth.');

        $booth = Booth::where('partner_id', $partner->id)->first();

        return response()->json(['data' => $booth ? $this->boothArray($booth) : null]);
    }

    public function updateBooth(Request $request): JsonResponse
    {
        $partner = Partner::findOrFail($request->attributes->get('partner_id'));
        abort_unless($partner->type === 'exhibitor', 422, 'Only exhibitors have a booth.');

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:60'],
            'type' => ['nullable', Rule::in(['physical', 'virtual'])],
            'resources' => ['nullable', 'array'],
        ]);

        $booth = Booth::updateOrCreate(
            ['partner_id' => $partner->id],
            [
                'event_id' => $partner->event_id,
                'organization_id' => $partner->organization_id,
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
