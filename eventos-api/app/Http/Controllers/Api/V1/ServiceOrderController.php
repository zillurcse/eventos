<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ServiceCategory;
use App\Models\ServiceOrder;
use App\Models\ServiceRequest;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Organizer side of Services › Requested Services (§ Services). Lists the
 * orders exhibitors submitted from their "Request Service" area, and lets the
 * organizer approve or reject them line-by-line (an order with mixed lines is
 * reported as "partial") or in one go.
 *
 * Orders per event are few enough to hand the whole filtered set to the client,
 * which does the grouping/paging — so the tab counts stay stable while the user
 * flips between Grouped and List.
 */
class ServiceOrderController extends Controller
{
    /** Every order on the event, plus the headline stats and filter options. */
    public function index(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $query = ServiceOrder::with(['exhibitor:id,uuid,name', 'requests.serviceItem.category'])
            ->where('event_id', $event->id);

        if ($term = trim((string) $request->query('search'))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(fn ($q) => $q
                ->where('order_number', 'ilike', $like)
                ->orWhereHas('exhibitor', fn ($e) => $e->where('name', 'ilike', $like))
                ->orWhereHas('requests.serviceItem', fn ($s) => $s->where('title', 'ilike', $like)));
        }
        if ($exhibitor = $request->query('exhibitor')) {
            $query->whereHas('exhibitor', fn ($e) => $e->where('uuid', $exhibitor));
        }
        if ($category = $request->query('category')) {
            $query->whereHas('requests.serviceItem', fn ($s) => $s->where('category_id', (int) $category));
        }

        $orders = $query->orderByDesc('id')->limit(2000)->get()
            ->map(fn (ServiceOrder $order) => $this->summarize($order))
            ->values();

        return response()->json([
            'data' => $orders,
            'stats' => [
                'total' => $orders->count(),
                'pending' => $orders->where('status', 'pending')->count(),
                'partial' => $orders->where('status', 'partial')->count(),
                'approved' => $orders->where('status', 'approved')->count(),
                'rejected' => $orders->where('status', 'rejected')->count(),
            ],
            'exhibitors' => $this->exhibitorOptions($event),
            'categories' => ServiceCategory::where('event_id', $event->id)
                ->orderBy('position')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** One order with its lines — the "Service Request Details" drawer. */
    public function show(string $order): JsonResponse
    {
        return response()->json(['data' => $this->detail($this->findOrder($order))]);
    }

    /** Approve / reject every line of an order at once. */
    public function update(Request $request, string $order): JsonResponse
    {
        $model = $this->findOrder($order);

        $data = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected'],
        ]);

        $model->requests()->update(['status' => $data['status']]);

        return response()->json(['data' => $this->detail($model->fresh(['exhibitor', 'requests.serviceItem.category']))]);
    }

    /** Approve / reject a single line; this is what produces a partial order. */
    public function updateLine(Request $request, string $line): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected'],
        ]);

        $model = ServiceRequest::where('uuid', $line)->firstOrFail();
        $model->update(['status' => $data['status']]);

        return response()->json(['data' => $this->detail($this->findOrder($model->serviceOrder->uuid))]);
    }

    /** Invoice-style PDF of the order, rendered server-side with dompdf. */
    public function pdf(string $order): Response
    {
        $detail = $this->detail($this->findOrder($order));

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml(view('pdf.service-order', ['order' => $detail])->render());
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$detail['order_number'].'.pdf"',
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function findOrder(string $uuid): ServiceOrder
    {
        return ServiceOrder::with(['exhibitor:id,uuid,name', 'requests.serviceItem.category'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /** Row shape for the list/grouped tables. */
    private function summarize(ServiceOrder $order): array
    {
        $lines = $order->requests;
        $totals = $this->totals($lines);

        return [
            'id' => $order->uuid,
            'order_number' => $order->order_number,
            'date' => optional($order->submitted_at ?? $order->created_at)->toIso8601String(),
            'status' => $this->statusOf($lines),
            'exhibitor' => [
                'id' => $order->exhibitor?->uuid,
                'name' => $order->exhibitor?->name,
            ],
            'lines_count' => $lines->count(),
            'counts' => [
                'pending' => $lines->where('status', 'pending')->count(),
                'approved' => $lines->where('status', 'approved')->count(),
                'rejected' => $lines->where('status', 'rejected')->count(),
            ],
            'currency' => $lines->first()?->currency ?? 'USD',
            'subtotal' => $totals['subtotal'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
        ];
    }

    /** Row shape plus the line items, for the detail drawer and the PDF. */
    private function detail(ServiceOrder $order): array
    {
        return $this->summarize($order) + [
            'items' => $order->requests->map(fn (ServiceRequest $line) => [
                'id' => $line->uuid,
                'name' => $line->serviceItem?->title,
                'description' => $line->serviceItem?->description,
                'image' => $line->serviceItem?->image,
                'unit' => $line->serviceItem?->unit,
                'category' => $line->serviceItem?->category?->name,
                'quantity' => $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'line_total' => round($line->unit_price * $line->quantity, 2),
                'tax' => (float) ($line->serviceItem?->tax ?? 0),
                'currency' => $line->currency,
                'status' => $line->status,
            ])->values(),
        ];
    }

    private function statusOf(Collection $lines): string
    {
        return ServiceOrder::rollUpStatus(
            $lines->where('status', 'pending')->count(),
            $lines->where('status', 'approved')->count(),
            $lines->where('status', 'rejected')->count(),
        );
    }

    /** Rejected lines still count: the order keeps its as-submitted value. */
    private function totals(Collection $lines): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($lines as $line) {
            $lineTotal = (float) $line->unit_price * $line->quantity;
            $subtotal += $lineTotal;
            $taxTotal += $lineTotal * ((float) ($line->serviceItem?->tax ?? 0)) / 100;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($taxTotal, 2),
            'total' => round($subtotal + $taxTotal, 2),
        ];
    }

    /** Exhibitors that have actually ordered something — the filter dropdown. */
    private function exhibitorOptions(Event $event): array
    {
        return DB::table('service_orders')
            ->join('exhibitors', 'exhibitors.id', '=', 'service_orders.exhibitor_id')
            ->where('service_orders.event_id', $event->id)
            ->whereNull('service_orders.deleted_at')
            ->distinct()
            ->orderBy('exhibitors.name')
            ->get(['exhibitors.uuid as id', 'exhibitors.name'])
            ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
            ->all();
    }
}
