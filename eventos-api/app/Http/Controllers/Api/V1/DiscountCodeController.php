<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\NormalizesTimestamps;
use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscountCodeController extends Controller
{
    use NormalizesTimestamps;

    public function store(Request $request): JsonResponse
    {
        $event = Event::where('uuid', $request->string('event'))->firstOrFail();

        $data = $request->validate([
            'event' => ['required', 'string'],
            'code' => ['required', 'string', 'max:60', Rule::unique('discount_codes')->where('event_id', $event->id)],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $data = $this->utcDates($data, ['expires_at']);

        $code = DiscountCode::create([
            'event_id' => $event->id,
            'code' => $data['code'],
            'type' => $data['type'],
            'value' => $data['value'],
            'max_uses' => $data['max_uses'] ?? null,
            'used' => 0,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return response()->json(['data' => [
            'code' => $code->code,
            'type' => $code->type,
            'value' => (int) $code->value,
            'max_uses' => $code->max_uses,
        ]], 201);
    }
}
