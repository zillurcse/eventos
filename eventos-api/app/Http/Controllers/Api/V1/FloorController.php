<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FloorResource;
use App\Models\Event;
use App\Models\Floor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Floor plans for the floor.expouse canvas editor. Event-scoped on index/store;
 * id-based on show/update/destroy (resolved here, not via route binding, so the
 * tenant GUC is already set and RLS doesn't hide the row at bind time). JSON
 * blobs are read with $request->input() because $request->validate() strips
 * nested keys.
 */
class FloorController extends Controller
{
    /** Canvas-state fields shared by store/update. */
    private const JSON_FIELDS = ['dimensions', 'floor_area', 'objects', 'dom_elements', 'offset'];

    public function index(string $uuid): AnonymousResourceCollection
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $floors = Floor::where('event_id', $event->id)
            ->orderBy('id')
            ->get();

        return FloorResource::collection($floors);
    }

    public function store(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $request->validate($this->rules(required: true));

        $floor = Floor::create([
            'event_id' => $event->id,
            'name' => $request->input('name'),
            'shape_type' => $request->input('shape_type', 'rectangular'),
            'dimensions' => $request->input('dimensions'),
            'floor_area' => $request->input('floor_area'),
            'objects' => $request->input('objects', []),
            'dom_elements' => $request->input('dom_elements', []),
            'offset' => $request->input('offset'),
            'zoom' => (int) $request->input('zoom', 1),
            'wall_generated' => $request->boolean('wall_generated'),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['data' => new FloorResource($floor)], 201);
    }

    public function show(int $floor): JsonResponse
    {
        $model = Floor::findOrFail($floor);

        return response()->json(['data' => new FloorResource($model)]);
    }

    public function update(Request $request, int $floor): JsonResponse
    {
        $model = Floor::findOrFail($floor);

        $request->validate($this->rules(required: false));

        foreach (['name', 'shape_type', 'zoom', ...self::JSON_FIELDS] as $key) {
            if ($request->has($key)) {
                $model->{$key} = $request->input($key);
            }
        }
        if ($request->has('wall_generated')) {
            $model->wall_generated = $request->boolean('wall_generated');
        }
        $model->updated_by = $request->user()?->id;
        $model->save();

        return response()->json(['data' => new FloorResource($model)]);
    }

    public function destroy(int $floor): JsonResponse
    {
        Floor::findOrFail($floor)->delete();

        return response()->json(['status' => 'success']);
    }

    private function rules(bool $required): array
    {
        return [
            'name' => [$required ? 'required' : 'sometimes', 'string', 'max:255'],
            'shape_type' => ['nullable', 'string', 'max:255'],
            'dimensions' => ['nullable', 'array'],
            'floor_area' => ['nullable', 'array'],
            'objects' => ['nullable', 'array'],
            'dom_elements' => ['nullable', 'array'],
            'offset' => ['nullable', 'array'],
            'zoom' => ['nullable', 'integer'],
            'wall_generated' => ['nullable', 'boolean'],
        ];
    }
}
