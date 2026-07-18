<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExhibitorLeadResource;
use App\Models\Event;
use App\Models\Exhibitor;
use App\Models\ExhibitorLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Organizer-side Lead Generation (Onsite). A read-only, event-wide view over
 * the leads every exhibitor booth captured: headline KPIs, per-exhibitor and
 * quality breakdowns, a filterable recent-leads table and a CSV export.
 * Editing stays in the exhibitor CRM — the organizer only observes.
 *
 * "Consented" = the lead is linked to an attendee participation (captured
 * in-app via badge scan / connect, i.e. the attendee shared their profile) or
 * carries an explicit meta.consent flag from an import.
 */
class LeadGenerationController extends Controller
{
    private const TOP_EXHIBITORS = 8;

    public function index(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $query = $this->filtered($request, $event)
            ->with(['scannedBy.contact', 'exhibitor']);

        $this->applySort($query, (string) $request->query('sort', 'recent'));

        $perPage = min(max((int) $request->query('per_page', 10), 5), 100);
        $page = $query->paginate($perPage);

        $rows = collect($page->items())->map(fn (ExhibitorLead $l) => (new ExhibitorLeadResource($l))->toArray($request) + [
            'exhibitor' => $l->exhibitor ? [
                'id' => $l->exhibitor->uuid,
                'name' => $l->exhibitor->name,
                'type' => $l->exhibitor->type,
            ] : null,
        ]);

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'insights' => $this->insights($event),
        ]);
    }

    /** Export the current (filtered) selection across all booths to CSV. */
    public function export(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $leads = $this->filtered($request, $event)
            ->with(['scannedBy.contact', 'exhibitor'])
            ->orderByDesc('id')
            ->get();

        $columns = ['Name', 'Email', 'Phone', 'Company', 'Job title', 'Exhibitor', 'Rating', 'Status', 'Source', 'Scanned by', 'Consent', 'Notes', 'Captured at'];
        $rows = $leads->map(function (ExhibitorLead $l) {
            $rep = $l->scannedBy?->contact;
            $repName = $rep ? (trim(($rep->first_name ?? '').' '.($rep->last_name ?? '')) ?: $rep->email) : '';

            return [
                $l->name, $l->email, $l->phone, $l->company, $l->job_title,
                $l->exhibitor?->name, $l->rating, $l->status, $l->source, $repName,
                $this->consented($l) ? 'yes' : 'no', $l->notes,
                optional($l->scanned_at ?? $l->created_at)->toDateTimeString(),
            ];
        });

        return response()->json(['data' => [
            'csv' => $this->toCsv($columns, $rows),
            'filename' => 'lead-generation-'.now()->format('Y-m-d').'.csv',
            'count' => $leads->count(),
        ]]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** Table/export filters. Insights always cover the whole event. */
    private function filtered(Request $request, Event $event)
    {
        $query = ExhibitorLead::where('event_id', $event->id);

        if ($term = trim((string) $request->query('search'))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)
                ->orWhere('company', 'ilike', $like)
                ->orWhereHas('exhibitor', fn ($e) => $e->where('name', 'ilike', $like)));
        }
        if (($rating = $request->query('rating')) && in_array($rating, ExhibitorLead::RATINGS, true)) {
            $query->where('rating', $rating);
        }
        if (($status = $request->query('status')) && in_array($status, ExhibitorLead::STATUSES, true)) {
            $query->where('status', $status);
        }
        if ($exhibitor = $request->query('exhibitor')) {
            $query->whereHas('exhibitor', fn ($e) => $e->where('uuid', $exhibitor));
        }

        return $query;
    }

    private function insights(Event $event): array
    {
        $leads = ExhibitorLead::where('event_id', $event->id)
            ->get(['id', 'exhibitor_id', 'rating', 'participation_id', 'meta']);

        $total = $leads->count();
        $hot = $leads->where('rating', 'hot')->count();
        $consented = $leads->filter(fn ($l) => $this->consented($l))->count();
        $pct = fn (int $n) => $total > 0 ? (int) round($n / $total * 100) : 0;

        $exhibitors = Exhibitor::where('event_id', $event->id)->get(['id', 'uuid', 'name', 'type']);
        $byExhibitor = $leads->groupBy('exhibitor_id');
        $capturing = $byExhibitor->keys()->filter(fn ($id) => $exhibitors->contains('id', $id))->count();

        $capturingRows = $exhibitors
            ->map(fn (Exhibitor $e) => [
                'id' => $e->uuid,
                'name' => $e->name,
                'type' => $e->type,
                'leads' => $byExhibitor->get($e->id)?->count() ?? 0,
                'hot' => $byExhibitor->get($e->id)?->where('rating', 'hot')->count() ?? 0,
            ])
            ->filter(fn ($row) => $row['leads'] > 0)
            ->sortByDesc('leads')
            ->values();

        return [
            'totals' => [
                'leads' => $total,
                'hot' => $hot,
                'hot_pct' => $pct($hot),
                'warm' => $leads->where('rating', 'warm')->count(),
                'cold' => $leads->where('rating', 'cold')->count(),
                'consented' => $consented,
                'consent_rate' => $pct($consented),
                'exhibitors' => $exhibitors->count(),
                'exhibitors_capturing' => $capturing,
                'capture_rate' => $exhibitors->count() > 0 ? (int) round($capturing / $exhibitors->count() * 100) : 0,
            ],
            'by_exhibitor' => $capturingRows->take(self::TOP_EXHIBITORS),
            // Every booth with at least one lead — feeds the table's filter.
            'exhibitors' => $capturingRows->map(fn ($r) => ['id' => $r['id'], 'name' => $r['name']]),
        ];
    }

    private function consented(ExhibitorLead $lead): bool
    {
        return $lead->participation_id !== null || (bool) ($lead->meta['consent'] ?? false);
    }

    private function applySort($query, string $sort): void
    {
        match ($sort) {
            'name' => $query->orderBy('name'),
            'company' => $query->orderBy('company'),
            'rating' => $query->orderByRaw("array_position(ARRAY['hot','warm','cold'], rating)"),
            'oldest' => $query->orderBy('id'),
            default => $query->orderByDesc('id'),
        };
    }

    private function toCsv(array $columns, $rows): string
    {
        $escape = fn ($v) => '"'.str_replace('"', '""', (string) $v).'"';
        $lines = [implode(',', array_map($escape, $columns))];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map($escape, $row));
        }

        return implode("\r\n", $lines);
    }
}
