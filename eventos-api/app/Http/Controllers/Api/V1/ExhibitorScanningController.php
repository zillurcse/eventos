<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\CheckInStation;
use App\Models\Event;
use App\Models\Exhibitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Organizer-side Exhibitors Scanning (Onsite). A "booth" here is a
 * check_in_stations row of type "booth"; every attendee tap at a booth is a
 * check_ins row (direction "in") recorded by the existing scan pipeline. This
 * is booth-level footfall — deliberately separate from qualified lead capture
 * (that lives in the exhibitor CRM / Lead Generation view).
 *
 * The controller exposes the analytics (traffic leaderboard, a booth × hour
 * visit-intensity heatmap, and per-booth totals measured against total gate
 * entries) plus booth CRUD. A booth may link to an exhibitor and mark itself
 * lead-generating, so a staff scan there can also capture a lead, not just a
 * visit.
 */
class ExhibitorScanningController extends Controller
{
    private const LEADERBOARD = 5;   // booths shown in the traffic leaderboard
    private const HEATMAP_ROWS = 6;  // busiest booths shown on the heatmap

    public function index(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $booths = CheckInStation::where('event_id', $event->id)
            ->where('type', 'booth')
            ->orderBy('id')
            ->get();

        $scans = CheckIn::where('event_id', $event->id)
            ->whereNotNull('scanned_at')
            ->whereIn('station_id', $booths->pluck('id')->all())
            ->get(['id', 'station_id', 'participation_id', 'scanned_at']);

        // Total venue-gate entries — the denominator for "VS. gate entries".
        $gateEntries = $this->gateEntries($event);

        $exhibitors = Exhibitor::where('event_id', $event->id)
            ->exhibitors()
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'type']);
        $exhibitorsById = $exhibitors->keyBy('id');

        $rows = $booths
            ->map(fn (CheckInStation $b) => $this->boothRow($b, $scans->where('station_id', $b->id), $gateEntries, $exhibitorsById))
            ->sortByDesc('total_scans')
            ->values();

        return response()->json([
            'data' => [
                'totals' => [
                    'booths' => $booths->count(),
                    'total_scans' => $scans->count(),
                    'unique_visitors' => $scans->pluck('participation_id')->unique()->count(),
                    'scans_today' => $scans->where('scanned_at', '>=', now()->startOfDay())->count(),
                    'gate_entries' => $gateEntries,
                ],
                'leaderboard' => $rows->take(self::LEADERBOARD)
                    ->map(fn ($r) => ['id' => $r['id'], 'code' => $r['code'], 'exhibitor' => $r['exhibitor'], 'total_scans' => $r['total_scans']])
                    ->values(),
                'heatmap' => $this->heatmap($rows, $scans),
                'booths' => $rows,
                'halls' => $booths->pluck('location')->filter()->unique()->sort()->values(),
                'exhibitors' => $exhibitors->map(fn (Exhibitor $e) => ['id' => $e->uuid, 'name' => $e->name])->values(),
            ],
        ]);
    }

    public function store(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $data = $this->validated($request, $event);

        $booth = CheckInStation::create([
            'event_id' => $event->id,
            'name' => $data['code'],
            'location' => $data['hall'] ?? null,
            'type' => 'booth',
            'meta' => $this->metaFrom($data),
        ]);

        return response()->json(['data' => $this->freshRow($booth)], 201);
    }

    public function update(Request $request, string $uuid, int $station): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $booth = $this->booth($event, $station);
        $data = $this->validated($request, $event);

        $booth->update([
            'name' => $data['code'],
            'location' => $data['hall'] ?? null,
            'meta' => $this->metaFrom($data, $booth->meta ?? []),
        ]);

        return response()->json(['data' => $this->freshRow($booth->fresh())]);
    }

    /** Scan history survives (check_ins.station_id nulls on delete). */
    public function destroy(string $uuid, int $station): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $this->booth($event, $station)->delete();

        return response()->json(['message' => 'Booth removed.']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function validated(Request $request, Event $event): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:60'],
            'hall' => ['nullable', 'string', 'max:180'],
            'exhibitor' => ['nullable', 'string'],   // exhibitor uuid
            'scan_mode' => ['required', 'in:staff_kiosk,staff,kiosk'],
            'lead_generation' => ['boolean'],
        ]);

        $exhibitor = ! empty($data['exhibitor'])
            ? Exhibitor::where('event_id', $event->id)->where('uuid', $data['exhibitor'])->first()
            : null;

        $data['exhibitor_id'] = $exhibitor?->id;
        $data['exhibitor_name'] = $exhibitor?->name;

        return $data;
    }

    private function metaFrom(array $data, array $existing = []): array
    {
        return array_merge($existing, [
            'exhibitor_id' => $data['exhibitor_id'] ?? null,
            'exhibitor_name' => $data['exhibitor_name'] ?? null,
            'scan_mode' => $data['scan_mode'],
            'lead_generation' => (bool) ($data['lead_generation'] ?? false),
        ]);
    }

    private function booth(Event $event, int $station): CheckInStation
    {
        return CheckInStation::where('event_id', $event->id)
            ->where('type', 'booth')
            ->findOrFail($station);
    }

    /** Total attendee entries recorded across every venue gate. */
    private function gateEntries(Event $event): int
    {
        $gateIds = CheckInStation::where('event_id', $event->id)
            ->where('type', 'entrance')
            ->pluck('id');

        return CheckIn::where('event_id', $event->id)
            ->whereIn('station_id', $gateIds)
            ->where('direction', 'in')
            ->count();
    }

    private function boothRow(CheckInStation $booth, Collection $scans, int $gateEntries, Collection $exhibitorsById): array
    {
        $total = $scans->count();
        $meta = $booth->meta ?? [];
        $exhibitor = isset($meta['exhibitor_id']) ? $exhibitorsById->get($meta['exhibitor_id']) : null;

        return [
            'id' => $booth->id,
            'code' => $booth->name,
            'hall' => $booth->location,
            'exhibitor_id' => $exhibitor?->uuid,
            'exhibitor' => $exhibitor?->name ?? ($meta['exhibitor_name'] ?? null),
            'exhibitor_type' => $exhibitor?->type,
            'scan_mode' => $meta['scan_mode'] ?? 'staff_kiosk',
            'lead_generation' => (bool) ($meta['lead_generation'] ?? false),
            'total_scans' => $total,
            'unique' => $scans->pluck('participation_id')->unique()->count(),
            'scans_today' => $scans->where('scanned_at', '>=', now()->startOfDay())->count(),
            'vs_gate' => $gateEntries > 0 ? (int) round($total / $gateEntries * 100) : 0,
        ];
    }

    /** Busiest booths × hour-of-day scan counts, over the hours booths saw traffic. */
    private function heatmap(Collection $rows, Collection $scans): array
    {
        $top = $rows->where('total_scans', '>', 0)->take(self::HEATMAP_ROWS);
        if ($top->isEmpty() || $scans->isEmpty()) {
            return ['hours' => [], 'rows' => []];
        }

        $hoursSeen = $scans->map(fn (CheckIn $s) => (int) $s->scanned_at->format('G'))->unique();
        $hours = range($hoursSeen->min(), $hoursSeen->max());

        $byStationHour = $scans->groupBy('station_id')
            ->map(fn (Collection $g) => $g
                ->groupBy(fn (CheckIn $s) => (int) $s->scanned_at->format('G'))
                ->map->count());

        return [
            'hours' => array_values($hours),
            'rows' => $top->map(fn ($r) => [
                'id' => $r['id'],
                'code' => $r['code'],
                'cells' => array_map(fn ($h) => [
                    'hour' => $h,
                    'scans' => (int) ($byStationHour->get($r['id'])?->get($h) ?? 0),
                ], $hours),
            ])->values(),
        ];
    }

    /** A just-saved booth, without scan stats (clients reload the overview). */
    private function freshRow(CheckInStation $booth): array
    {
        $exhibitors = Exhibitor::where('event_id', $booth->event_id)
            ->get(['id', 'uuid', 'name', 'type'])
            ->keyBy('id');

        return $this->boothRow($booth, collect(), 0, $exhibitors);
    }
}
