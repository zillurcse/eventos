<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CheckInStation;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckInStationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CheckInStation::query()->latest('id');

        if ($request->filled('event')) {
            $event = Event::where('uuid', $request->string('event'))->firstOrFail();
            $query->where('event_id', $event->id);
        }

        return response()->json(['data' => $query->get(['id', 'name', 'location', 'type'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string'],
            'name' => ['required', 'string', 'max:180'],
            'location' => ['nullable', 'string', 'max:180'],
            'type' => ['nullable', 'in:entrance,session,booth'],
        ]);

        $event = Event::where('uuid', $data['event'])->firstOrFail();

        $station = CheckInStation::create([
            'event_id' => $event->id,
            'name' => $data['name'],
            'location' => $data['location'] ?? null,
            'type' => $data['type'] ?? 'entrance',
        ]);

        return response()->json(['data' => $station->only('id', 'name', 'location', 'type')], 201);
    }
}
