<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrackResource;
use App\Models\Event;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TrackController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Track::query()->orderBy('sort_order');

        if ($request->filled('event')) {
            $event = Event::where('uuid', $request->string('event'))->firstOrFail();
            $query->where('event_id', $event->id);
        }

        return TrackResource::collection($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string'], // event uuid
            'name' => ['required', 'string', 'max:180'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $event = Event::where('uuid', $data['event'])->firstOrFail();

        $track = Track::create([
            'event_id' => $event->id,
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => new TrackResource($track)], 201);
    }
}
