<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceCategoryResource;
use App\Models\Event;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceCategoryController extends Controller
{
    public function index(string $uuid): AnonymousResourceCollection
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $categories = ServiceCategory::where('event_id', $event->id)
            ->withCount('items')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return ServiceCategoryResource::collection($categories);
    }

    public function store(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $category = ServiceCategory::create([
            'event_id' => $event->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        return response()->json(['data' => new ServiceCategoryResource($category)], 201);
    }

    public function update(Request $request, int $category): JsonResponse
    {
        // Resolve here (not via route binding) so the tenant GUC is already set
        // and RLS doesn't hide the row at bind time.
        $model = ServiceCategory::findOrFail($category);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $model->update($data);

        return response()->json(['data' => new ServiceCategoryResource($model)]);
    }

    public function destroy(int $category): JsonResponse
    {
        ServiceCategory::findOrFail($category)->delete();

        return response()->json(['status' => 'success']);
    }
}
