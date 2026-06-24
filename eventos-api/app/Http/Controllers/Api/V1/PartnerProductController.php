<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartnerProductResource;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartnerProductController extends Controller
{
    public function index(string $partnerUuid): AnonymousResourceCollection
    {
        $partner = Partner::where('uuid', $partnerUuid)->firstOrFail();

        return PartnerProductResource::collection($partner->products()->latest('id')->get());
    }

    public function store(string $partnerUuid, Request $request): JsonResponse
    {
        $partner = Partner::where('uuid', $partnerUuid)->firstOrFail();

        $product = $partner->products()->create($this->validated($request));

        return response()->json(['data' => new PartnerProductResource($product)], 201);
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
