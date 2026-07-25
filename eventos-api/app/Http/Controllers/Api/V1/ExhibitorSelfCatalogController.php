<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExhibitorProductResource;
use App\Models\Exhibitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exhibitor-admin self-service catalog: the booth manages its own Documents,
 * Projects and Products. The active exhibitor is resolved by
 * ResolveExhibitorAdmin (tenant GUC = its org); everything is scoped to
 * `exhibitor_id` so a member can only touch their own booth's rows.
 */
class ExhibitorSelfCatalogController extends Controller
{
    // ── Documents ───────────────────────────────────────────────────────────
    public function documents(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->exhibitor($request)->documents()->latest('id')->get()
            ->map(fn ($d) => ['id' => $d->id, 'title' => $d->title, 'url' => $d->url, 'visibility' => $d->visibility])
            ->values()]);
    }

    public function storeDocument(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'url' => ['nullable', 'string', 'max:500'],
            'file_id' => ['nullable', 'integer'],
            'visibility' => ['nullable', 'in:all,members,private'],
        ]);

        $doc = $this->exhibitor($request)->documents()->create([
            'title' => $data['title'],
            'url' => $data['url'] ?? null,
            'file_id' => $data['file_id'] ?? null,
            'visibility' => $data['visibility'] ?? 'all',
        ]);

        return response()->json(['data' => ['id' => $doc->id, 'title' => $doc->title, 'url' => $doc->url, 'visibility' => $doc->visibility]], 201);
    }

    public function destroyDocument(Request $request, int $document): JsonResponse
    {
        $this->exhibitor($request)->documents()->whereKey($document)->firstOrFail()->delete();

        return response()->json(['message' => 'Document removed.']);
    }

    // ── Projects ────────────────────────────────────────────────────────────
    public function projects(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->exhibitor($request)->projects()->latest('id')->get()
            ->map(fn ($p) => $this->presentProject($p))
            ->values()]);
    }

    public function storeProject(Request $request): JsonResponse
    {
        $project = $this->exhibitor($request)->projects()->create(ExhibitorProjectController::validated($request));

        return response()->json(['data' => $this->presentProject($project)], 201);
    }

    public function updateProject(Request $request, int $project): JsonResponse
    {
        $p = $this->exhibitor($request)->projects()->whereKey($project)->firstOrFail();
        $p->update(ExhibitorProjectController::validated($request));

        return response()->json(['data' => $this->presentProject($p)]);
    }

    public function destroyProject(Request $request, int $project): JsonResponse
    {
        $this->exhibitor($request)->projects()->whereKey($project)->firstOrFail()->delete();

        return response()->json(['message' => 'Project removed.']);
    }

    // ── Products ────────────────────────────────────────────────────────────
    public function products(Request $request): JsonResponse
    {
        return response()->json(['data' => ExhibitorProductResource::collection(
            $this->exhibitor($request)->products()->latest('id')->get(),
        )]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $product = $this->exhibitor($request)->products()->create(ExhibitorProductController::validated($request));

        return response()->json(['data' => new ExhibitorProductResource($product)], 201);
    }

    public function updateProduct(Request $request, int $product): JsonResponse
    {
        $p = $this->exhibitor($request)->products()->whereKey($product)->firstOrFail();
        $p->update(ExhibitorProductController::validated($request));

        return response()->json(['data' => new ExhibitorProductResource($p)]);
    }

    public function destroyProduct(Request $request, int $product): JsonResponse
    {
        $this->exhibitor($request)->products()->whereKey($product)->firstOrFail()->delete();

        return response()->json(['message' => 'Product removed.']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────
    private function exhibitor(Request $request): Exhibitor
    {
        return Exhibitor::findOrFail($request->attributes->get('exhibitor_id'));
    }

    private function presentProject($p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'status' => $p->status,
            'meta' => $p->meta,
        ];
    }
}
