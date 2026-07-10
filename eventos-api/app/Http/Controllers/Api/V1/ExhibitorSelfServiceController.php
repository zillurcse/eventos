<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceRequestResource;
use App\Models\Exhibitor;
use App\Models\ServiceCategory;
use App\Models\ServiceItem;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Exhibitor-admin "Request Service" area (§6.3). The booth browses the event's
 * Services catalogue (service_items, owned by the organizer) and orders items;
 * each order is a service_requests row. The active exhibitor is resolved by
 * ResolveExhibitorAdmin (tenant GUC = its org).
 */
class ExhibitorSelfServiceController extends Controller
{
    /** The event's active catalogue, annotated with the booth's current quantities. */
    public function catalog(Request $request): JsonResponse
    {
        $exhibitor = $this->exhibitor($request);

        $requested = ServiceRequest::where('exhibitor_id', $exhibitor->id)
            ->pluck('quantity', 'service_item_id');

        $items = ServiceItem::with('category')
            ->where('event_id', $exhibitor->event_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (ServiceItem $it) => [
                'id' => $it->id,
                'name' => $it->title,
                'unit' => $it->unit,
                'rate' => (float) $it->rate,
                'currency' => $it->currency,
                'tax' => (float) $it->tax,
                'image' => $it->image,
                'description' => $it->description,
                'category_id' => $it->category_id,
                'category' => $it->category?->name,
                'added' => (int) ($requested[$it->id] ?? 0),
            ])
            ->values();

        return response()->json(['data' => $items]);
    }

    public function categories(Request $request): JsonResponse
    {
        $exhibitor = $this->exhibitor($request);

        $categories = ServiceCategory::where('event_id', $exhibitor->event_id)
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $categories]);
    }

    /** The booth's own requested services (table), with search / sort / pagination. */
    public function index(Request $request): JsonResponse
    {
        $exhibitor = $this->exhibitor($request);

        $query = ServiceRequest::with('serviceItem.category')
            ->where('exhibitor_id', $exhibitor->id);

        if ($term = trim((string) $request->query('search'))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->whereHas('serviceItem', fn ($q) => $q->where('title', 'ilike', $like));
        }

        $query->orderBy('id', $request->query('sort') === 'oldest' ? 'asc' : 'desc');

        $perPage = min(max((int) $request->query('per_page', 10), 5), 100);
        $page = $query->paginate($perPage);

        return response()->json([
            'data' => ServiceRequestResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
        ]);
    }

    /** Submit a basket: upsert one request line per item (0 quantity removes it). */
    public function store(Request $request): JsonResponse
    {
        $exhibitor = $this->exhibitor($request);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        // Only items that belong to this booth's event may be ordered.
        $valid = ServiceItem::where('event_id', $exhibitor->event_id)
            ->where('is_active', true)
            ->whereIn('id', collect($data['items'])->pluck('service_item_id'))
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($data, $valid, $exhibitor) {
            foreach ($data['items'] as $line) {
                $item = $valid->get($line['service_item_id']);
                if (! $item) {
                    continue;
                }

                if ($line['quantity'] < 1) {
                    ServiceRequest::where('exhibitor_id', $exhibitor->id)
                        ->where('service_item_id', $item->id)
                        ->delete();

                    continue;
                }

                ServiceRequest::updateOrCreate(
                    ['exhibitor_id' => $exhibitor->id, 'service_item_id' => $item->id],
                    [
                        'organization_id' => $exhibitor->organization_id,
                        'event_id' => $exhibitor->event_id,
                        'quantity' => $line['quantity'],
                        'unit_price' => $item->rate,
                        'currency' => $item->currency,
                        'status' => 'pending',
                    ],
                );
            }
        });

        return $this->index($request);
    }

    public function update(Request $request, string $serviceRequest): JsonResponse
    {
        $model = $this->find($request, $serviceRequest);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $model->update(['quantity' => $data['quantity']]);

        return response()->json(['data' => new ServiceRequestResource($model->load('serviceItem.category'))]);
    }

    public function destroy(Request $request, string $serviceRequest): JsonResponse
    {
        $this->find($request, $serviceRequest)->delete();

        return response()->json(['message' => 'Service request removed.']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function exhibitor(Request $request): Exhibitor
    {
        return Exhibitor::findOrFail($request->attributes->get('exhibitor_id'));
    }

    private function find(Request $request, string $uuid): ServiceRequest
    {
        return ServiceRequest::where('exhibitor_id', $request->attributes->get('exhibitor_id'))
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
