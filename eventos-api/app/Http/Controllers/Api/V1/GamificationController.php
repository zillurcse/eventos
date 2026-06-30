<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GamificationResource;
use App\Models\Event;
use App\Models\Gamification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-event gamification config (Communication → Gamification). Singleton per
 * event — show + update only (no list / delete).
 */
class GamificationController extends Controller
{
    public function show(string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $config = Gamification::firstOrCreate(['event_id' => $event->id]);

        return response()->json(['data' => new GamificationResource($config)]);
    }

    public function update(string $uuid, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'scores' => ['sometimes', 'array'],
            'scores.*' => ['integer', 'min:0', 'max:100000'],
            'award_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'award_description' => ['sometimes', 'nullable', 'string'],
        ]);

        $config = Gamification::firstOrCreate(['event_id' => $event->id]);
        $config->fill($data)->save();

        return response()->json(['data' => new GamificationResource($config->fresh())]);
    }
}
