<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\CheckInStation;
use App\Models\Event;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Organizer-side Gates Scanning (Onsite). Gates are check_in_stations of type
 * "entrance"; every badge scan at a gate is a check_ins row (direction in/out)
 * recorded by the existing POST /check-in/scan pipeline. This controller adds
 * the analytics view (live occupancy, hourly entries, gate-wise detail),
 * gate CRUD, and the no-show list of registered attendees who never scanned in.
 *
 * A gate's "rating" is derived, not stored: entries per scanning unit
 * (staff + kiosks) today, compared with the average across gates — a gate
 * under half the average pace is flagged "slow".
 */
class GateScanningController extends Controller
{
    private const HOURLY_DAYS = 3;   // chart window: last N active days

    public function index(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $gates = CheckInStation::where('event_id', $event->id)
            ->where('type', 'entrance')
            ->orderBy('id')
            ->get();

        $scans = CheckIn::where('event_id', $event->id)
            ->whereNotNull('scanned_at')
            ->whereNull('session_id') // venue gates only, not session-room scans
            ->whereIn('station_id', $gates->pluck('id')->all()) // exclude booth footfall
            ->get(['id', 'station_id', 'participation_id', 'direction', 'scanned_at']);

        $entries = $scans->where('direction', 'in');
        $exits = $scans->where('direction', 'out');
        $today = now()->startOfDay();

        $registered = Participation::where('event_id', $event->id)->attendees()->count();
        $scannedInIds = $entries->pluck('participation_id')->unique();
        $showedUp = Participation::where('event_id', $event->id)->attendees()
            ->where(fn ($q) => $q->whereIn('id', $scannedInIds)->orWhereNotNull('checked_in_at'))
            ->count();
        $noShows = max(0, $registered - $showedUp);

        return response()->json([
            'data' => [
                'totals' => [
                    'inside' => max(0, $entries->count() - $exits->count()),
                    'entries_today' => $entries->where('scanned_at', '>=', $today)->count(),
                    'exits_today' => $exits->where('scanned_at', '>=', $today)->count(),
                    'registered' => $registered,
                    'no_shows' => $noShows,
                    'no_show_rate' => $registered > 0 ? (int) round($noShows / $registered * 100) : 0,
                ],
                'by_hour' => $this->hourly($entries),
                'gates' => $this->gateRows($gates, $scans),
            ],
        ]);
    }

    public function store(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $data = $this->validated($request);

        $gate = CheckInStation::create([
            'event_id' => $event->id,
            'name' => $data['name'],
            'location' => $data['location'] ?? null,
            'type' => 'entrance',
            'meta' => $this->metaFrom($data),
        ]);

        return response()->json(['data' => $this->freshRow($gate)], 201);
    }

    public function update(Request $request, string $uuid, int $station): JsonResponse
    {
        $gate = $this->gate($uuid, $station);
        $data = $this->validated($request);

        $gate->update([
            'name' => $data['name'],
            'location' => $data['location'] ?? null,
            'meta' => $this->metaFrom($data, $gate->meta ?? []),
        ]);

        return response()->json(['data' => $this->freshRow($gate->fresh())]);
    }

    /** Scan history survives (check_ins.station_id nulls on delete). */
    public function destroy(string $uuid, int $station): JsonResponse
    {
        $this->gate($uuid, $station)->delete();

        return response()->json(['message' => 'Gate removed.']);
    }

    /** Registered attendees who never scanned in at any gate. */
    public function noShows(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $query = $this->noShowQuery($event);

        if ($term = trim((string) $request->query('search'))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->whereHas('contact', fn ($c) => $c
                ->where('first_name', 'ilike', $like)
                ->orWhere('last_name', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)
                ->orWhere('company', 'ilike', $like));
        }

        $perPage = min(max((int) $request->query('per_page', 10), 5), 100);
        $page = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (Participation $p) => $this->noShowRow($p)),
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

    public function exportNoShows(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $rows = $this->noShowQuery($event)->orderByDesc('id')->get()
            ->map(function (Participation $p) {
                $r = $this->noShowRow($p);

                return [$r['name'], $r['email'], $r['phone'], $r['company'], $r['job_title'], $r['registered_at']];
            });

        $escape = fn ($v) => '"'.str_replace('"', '""', (string) $v).'"';
        $lines = [implode(',', array_map($escape, ['Name', 'Email', 'Phone', 'Company', 'Job title', 'Registered at']))];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map($escape, $row));
        }

        return response()->json(['data' => [
            'csv' => implode("\r\n", $lines),
            'filename' => 'no-shows-'.now()->format('Y-m-d').'.csv',
            'count' => $rows->count(),
        ]]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'location' => ['nullable', 'string', 'max:180'],
            'scan_mode' => ['required', 'in:staff_kiosk,staff,kiosk'],
            'staff' => ['required', 'integer', 'min:0', 'max:99'],
            'kiosks' => ['required', 'integer', 'min:0', 'max:99'],
            'direction' => ['required', 'in:both,in,out'],
            'reentry' => ['required', 'in:unlimited,single,daily'],
        ]);

        // Counts follow the chosen scan mode.
        if ($data['scan_mode'] === 'staff') {
            $data['kiosks'] = 0;
        }
        if ($data['scan_mode'] === 'kiosk') {
            $data['staff'] = 0;
        }
        if ($data['staff'] + $data['kiosks'] < 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'staff' => 'Assign at least one staff member or kiosk.',
            ]);
        }

        return $data;
    }

    private function metaFrom(array $data, array $existing = []): array
    {
        return array_merge($existing, [
            'staff' => $data['staff'],
            'kiosks' => $data['kiosks'],
            'direction' => $data['direction'],
            'reentry' => $data['reentry'],
        ]);
    }

    private function gate(string $uuid, int $station): CheckInStation
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        return CheckInStation::where('event_id', $event->id)
            ->where('type', 'entrance')
            ->findOrFail($station);
    }

    private function noShowQuery(Event $event)
    {
        $scannedIds = CheckIn::where('event_id', $event->id)
            ->where('direction', 'in')
            ->distinct()
            ->pluck('participation_id');

        return Participation::with('contact')
            ->where('event_id', $event->id)->attendees()
            ->whereNull('checked_in_at')
            ->whereNotIn('id', $scannedIds);
    }

    private function noShowRow(Participation $p): array
    {
        $c = $p->contact;

        return [
            'id' => $p->uuid,
            'name' => $c ? (trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: $c->email) : '—',
            'email' => $c?->email,
            'phone' => $c?->phone,
            'company' => $c?->company,
            'job_title' => $c?->job_title,
            'registered_at' => $p->created_at?->toIso8601String(),
        ];
    }

    /** Entries per hour, grouped by day, over the last few active days. */
    private function hourly(Collection $entries): array
    {
        $days = $entries
            ->groupBy(fn (CheckIn $s) => $s->scanned_at->toDateString())
            ->sortKeys()
            ->slice(-self::HOURLY_DAYS);

        return $days->map(function (Collection $scans, string $date) {
            $byHour = $scans->groupBy(fn (CheckIn $s) => (int) $s->scanned_at->format('G'));
            $from = min($byHour->keys()->all());
            $to = max($byHour->keys()->all());

            $hours = [];
            for ($h = $from; $h <= $to; $h++) {
                $hours[] = ['hour' => $h, 'entries' => $byHour->get($h)?->count() ?? 0];
            }

            return [
                'date' => $date,
                'label' => \Carbon\Carbon::parse($date)->format('M j'),
                'hours' => $hours,
            ];
        })->values()->all();
    }

    /** A just-saved gate, without scan stats (clients reload the overview). */
    private function freshRow(CheckInStation $gate): array
    {
        $row = $this->gateRow($gate, collect());
        unset($row['pace']);

        return $row + ['rating' => 'idle'];
    }

    private function gateRows(Collection $gates, Collection $scans): array
    {
        $rows = $gates->map(fn (CheckInStation $g) => $this->gateRow($g, $scans->where('station_id', $g->id)));

        // Rate a gate against the average pace (entries per unit today).
        $paces = $rows->pluck('pace')->filter(fn ($p) => $p > 0);
        $avg = $paces->isNotEmpty() ? $paces->avg() : 0;

        return $rows->map(function (array $row) use ($avg) {
            $row['rating'] = $row['entries_today'] === 0 ? 'idle'
                : ($avg > 0 && $row['pace'] < $avg / 2 ? 'slow' : 'following');
            unset($row['pace']);

            return $row;
        })->all();
    }

    private function gateRow(CheckInStation $gate, Collection $scans): array
    {
        $entries = $scans->where('direction', 'in')->count();
        $exits = $scans->where('direction', 'out')->count();
        $today = now()->startOfDay();
        $entriesToday = $scans->where('direction', 'in')->where('scanned_at', '>=', $today)->count();

        $staff = (int) ($gate->meta['staff'] ?? 0);
        $kiosks = (int) ($gate->meta['kiosks'] ?? 0);

        return [
            'id' => $gate->id,
            'name' => $gate->name,
            'location' => $gate->location,
            'staff' => $staff,
            'kiosks' => $kiosks,
            'mode' => $staff > 0 && $kiosks > 0 ? 'Staff + Kiosk'
                : ($kiosks > 0 ? 'Kiosk only' : 'Staff only'),
            'direction' => $gate->meta['direction'] ?? 'both',
            'reentry' => $gate->meta['reentry'] ?? 'unlimited',
            'entries' => $entries,
            'exits' => $exits,
            'inside' => max(0, $entries - $exits),
            'entries_today' => $entriesToday,
            'pace' => $entriesToday / max(1, $staff + $kiosks),
        ];
    }
}
