<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\NormalizesTimestamps;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketTypeResource;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketTypeController extends Controller
{
    use NormalizesTimestamps;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = TicketType::query()->latest('id');

        if ($request->filled('event')) {
            $event = Event::where('uuid', $request->string('event'))->firstOrFail();
            $query->where('event_id', $event->id);
        }

        return TicketTypeResource::collection($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'sales_start' => ['nullable', 'date'],
            'sales_end' => ['nullable', 'date', 'after_or_equal:sales_start'],
            'min_per_order' => ['nullable', 'integer', 'min:1'],
            'max_per_order' => ['nullable', 'integer', 'min:1'],
        ]);

        $event = Event::where('uuid', $data['event'])->firstOrFail();
        $data = $this->utcDates($data, ['sales_start', 'sales_end']);

        $type = TicketType::create([
            'event_id' => $event->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_cents' => $data['price_cents'] ?? 0,
            'currency' => $data['currency'] ?? 'USD',
            'quantity' => $data['quantity'] ?? null,
            'sales_start' => $data['sales_start'] ?? null,
            'sales_end' => $data['sales_end'] ?? null,
            'min_per_order' => $data['min_per_order'] ?? 1,
            'max_per_order' => $data['max_per_order'] ?? null,
            'is_active' => true,
        ]);

        return response()->json(['data' => new TicketTypeResource($type)], 201);
    }
}
