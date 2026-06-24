<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'], // RLS keeps this org-scoped
            'name' => ['required', 'string', 'max:180'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'floor' => ['nullable', 'string', 'max:60'],
        ]);

        // Inherit the venue's event scope (nullable for reusable venues).
        $venue = Venue::findOrFail($data['venue_id']);

        $room = Room::create($data + ['event_id' => $venue->event_id]);

        return response()->json(['data' => new RoomResource($room)], 201);
    }
}
