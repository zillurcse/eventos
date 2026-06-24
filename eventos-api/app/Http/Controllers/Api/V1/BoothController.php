<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booth;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoothController extends Controller
{
    public function store(string $partnerUuid, Request $request): JsonResponse
    {
        $partner = Partner::where('uuid', $partnerUuid)->firstOrFail();

        abort_unless($partner->type === 'exhibitor', 422, 'Booths can only be assigned to exhibitor partners.');

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:60'],
            'type' => ['nullable', 'in:physical,virtual'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'resources' => ['nullable', 'array'],
        ]);

        $booth = Booth::create([
            'partner_id' => $partner->id,
            'event_id' => $partner->event_id,
            'room_id' => $data['room_id'] ?? null,
            'code' => $data['code'] ?? null,
            'type' => $data['type'] ?? 'physical',
            'resources' => $data['resources'] ?? null,
        ]);

        return response()->json(['data' => $booth->only('id', 'code', 'type')], 201);
    }
}
