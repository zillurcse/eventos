<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExhibitorPackageResource;
use App\Models\Event;
use App\Models\ExhibitorPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ExhibitorPackageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ExhibitorPackage::query()->orderBy('rank');

        if ($request->filled('event')) {
            $event = Event::where('uuid', $request->string('event'))->firstOrFail();
            $query->where('event_id', $event->id);
        }

        return ExhibitorPackageResource::collection($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['nullable', 'in:exhibitor,sponsor,both'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'entitlements' => ['nullable', 'array'],
            'rank' => ['nullable', 'integer'],
        ]);

        $event = Event::where('uuid', $data['event'])->firstOrFail();

        $package = ExhibitorPackage::create([
            'event_id' => $event->id,
            'name' => $data['name'],
            'kind' => $data['kind'] ?? 'both',
            'price_cents' => $data['price_cents'] ?? 0,
            'currency' => $data['currency'] ?? 'USD',
            'entitlements' => $data['entitlements'] ?? null,
            'rank' => $data['rank'] ?? 0,
        ]);

        return response()->json(['data' => new ExhibitorPackageResource($package)], 201);
    }

    public function update(Request $request, ExhibitorPackage $exhibitorPackage): JsonResponse
    {
        $data = $request->validate([
            'name'         => ['sometimes', 'required', 'string', 'max:120'],
            'kind'         => ['nullable', 'in:exhibitor,sponsor,both'],
            'price_cents'  => ['nullable', 'integer', 'min:0'],
            'currency'     => ['nullable', 'string', 'size:3'],
            'entitlements' => ['nullable', 'array'],
            'rank'         => ['nullable', 'integer'],
        ]);

        $exhibitorPackage->update($data);

        return response()->json(['data' => new ExhibitorPackageResource($exhibitorPackage)]);
    }

    public function destroy(ExhibitorPackage $exhibitorPackage): Response
    {
        $exhibitorPackage->delete();

        return response()->noContent();
    }
}
