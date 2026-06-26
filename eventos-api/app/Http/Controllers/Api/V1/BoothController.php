<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booth;
use App\Models\Exhibitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoothController extends Controller
{
    public function store(string $exhibitorUuid, Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::where('uuid', $exhibitorUuid)->firstOrFail();

        abort_unless($exhibitor->type === 'exhibitor', 422, 'Booths can only be assigned to exhibitors.');

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:60'],
            'type' => ['nullable', 'in:physical,virtual'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'resources' => ['nullable', 'array'],
        ]);

        $booth = Booth::create([
            'exhibitor_id' => $exhibitor->id,
            'event_id' => $exhibitor->event_id,
            'room_id' => $data['room_id'] ?? null,
            'code' => $data['code'] ?? null,
            'type' => $data['type'] ?? 'physical',
            'resources' => $data['resources'] ?? null,
        ]);

        return response()->json(['data' => $booth->only('id', 'code', 'type')], 201);
    }
}
