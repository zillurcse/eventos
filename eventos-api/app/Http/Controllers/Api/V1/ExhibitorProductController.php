<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExhibitorProductResource;
use App\Models\Exhibitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExhibitorProductController extends Controller
{
    public function index(string $exhibitorUuid): AnonymousResourceCollection
    {
        $exhibitor = Exhibitor::where('uuid', $exhibitorUuid)->firstOrFail();

        return ExhibitorProductResource::collection($exhibitor->products()->latest('id')->get());
    }

    public function store(string $exhibitorUuid, Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::where('uuid', $exhibitorUuid)->firstOrFail();

        $product = $exhibitor->products()->create($this->validated($request));

        return response()->json(['data' => new ExhibitorProductResource($product)], 201);
    }

    public function destroy(string $exhibitorUuid, int $product): JsonResponse
    {
        $exhibitor = Exhibitor::where('uuid', $exhibitorUuid)->firstOrFail();

        $exhibitor->products()->whereKey($product)->firstOrFail()->delete();

        return response()->json(['message' => 'Product removed.']);
    }

    public static function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'meta' => ['nullable', 'array'],
        ]);
    }
}
