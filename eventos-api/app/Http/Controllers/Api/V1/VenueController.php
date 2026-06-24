<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VenueController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return VenueResource::collection(Venue::with('rooms')->latest('id')->get());
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => new VenueResource(Venue::with('rooms')->findOrFail($id))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        return response()->json(['data' => new VenueResource(Venue::create($data))], 201);
    }
}
