<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExhibitorProductResource;
use App\Http\Resources\ExhibitorResource;
use App\Models\Booth;
use App\Models\Exhibitor;
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

    public function show(Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::with(self::WITH)->findOrFail($request->attributes->get('exhibitor_id'));

        return response()->json(['data' => new ExhibitorResource($exhibitor)]);
    }

    /** Exhibitor edits its own public profile (name/description/website/logo/profile_data). */
    public function update(Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::findOrFail($request->attributes->get('exhibitor_id'));

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'profile_data' => ['sometimes', 'array'],
            // RLS scopes `files` to this org, so exists() also enforces ownership.
            'logo_file_id' => ['sometimes', 'nullable', 'integer', Rule::exists('files', 'id')],
        ]);

        $exhibitor->update($data);

        return response()->json(['data' => new ExhibitorResource($exhibitor->fresh(self::WITH))]);
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
