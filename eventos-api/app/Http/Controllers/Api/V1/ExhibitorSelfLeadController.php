<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExhibitorLeadResource;
use App\Models\Exhibitor;
use App\Models\ExhibitorLead;
use App\Models\ExhibitorMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Exhibitor lead-capture CRM (§6.3). The active exhibitor is resolved by
 * ResolveExhibitorAdmin (tenant GUC = its org); everything is scoped to
 * exhibitor_id so a booth only ever touches its own leads.
 */
class ExhibitorSelfLeadController extends Controller
{
    /** List with search / filters / sort / pagination, plus headline stats. */
    public function index(Request $request): JsonResponse
    {
        $exhibitorId = $request->attributes->get('exhibitor_id');

        $base = ExhibitorLead::where('exhibitor_id', $exhibitorId);

        // Filters (applied to a clone so stats stay computed over all leads).
        $query = (clone $base)->with('scannedBy.contact');

        if ($term = trim((string) $request->query('search'))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)
                ->orWhere('company', 'ilike', $like));
        }
        if (($rating = $request->query('rating')) && in_array($rating, ExhibitorLead::RATINGS, true)) {
            $query->where('rating', $rating);
        }
        if (($status = $request->query('status')) && in_array($status, ExhibitorLead::STATUSES, true)) {
            $query->where('status', $status);
        }
        if ($rep = $request->query('rep')) {
            // "unassigned" is a first-class filter on Team Connections: those are
            // the walk-ups nobody owns yet.
            $rep === 'unassigned'
                ? $query->whereNull('scanned_by_member_id')
                : $query->where('scanned_by_member_id', (int) $rep);
        }

        $this->applySort($query, (string) $request->query('sort', 'recent'));

        $perPage = min(max((int) $request->query('per_page', 10), 5), 100);
        $page = $query->paginate($perPage);

        return response()->json([
            'data' => ExhibitorLeadResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'stats' => $this->stats($exhibitorId),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::findOrFail($request->attributes->get('exhibitor_id'));
        $data = $this->validated($request, creating: true);

        $lead = $exhibitor->leads()->create($data + [
            'event_id' => $exhibitor->event_id,
            'source' => $data['source'] ?? 'manual',
            'scanned_at' => now(),
        ]);

        return response()->json(['data' => new ExhibitorLeadResource($lead->load('scannedBy.contact'))], 201);
    }

    public function update(Request $request, string $lead): JsonResponse
    {
        $model = $this->find($request, $lead);
        $model->update($this->validated($request, creating: false));

        return response()->json(['data' => new ExhibitorLeadResource($model->load('scannedBy.contact'))]);
    }

    public function destroy(Request $request, string $lead): JsonResponse
    {
        $this->find($request, $lead)->delete();

        return response()->json(['message' => 'Lead removed.']);
    }

    /** Export the current (filtered) selection to CSV; stamps exported_at. */
    public function export(Request $request): JsonResponse
    {
        $exhibitorId = $request->attributes->get('exhibitor_id');

        $query = ExhibitorLead::where('exhibitor_id', $exhibitorId)->with('scannedBy.contact');

        // Explicit id selection wins; otherwise export everything for the booth.
        $ids = (array) $request->input('ids', []);
        if ($ids) {
            $query->whereIn('uuid', $ids);
        }

        $leads = $query->orderByDesc('id')->get();

        $columns = ['Name', 'Email', 'Phone', 'Company', 'Job title', 'Rating', 'Status', 'Scanned by', 'Notes', 'Captured at'];
        $rows = $leads->map(function (ExhibitorLead $l) {
            $rep = $l->scannedBy?->contact;
            $repName = $rep ? (trim(($rep->first_name ?? '').' '.($rep->last_name ?? '')) ?: $rep->email) : '';

            return [
                $l->name, $l->email, $l->phone, $l->company, $l->job_title,
                $l->rating, $l->status, $repName, $l->notes,
                optional($l->scanned_at ?? $l->created_at)->toDateTimeString(),
            ];
        });

        $csv = $this->toCsv($columns, $rows);

        ExhibitorLead::whereKey($leads->pluck('id'))->update(['exported_at' => now()]);

        return response()->json(['data' => [
            'csv' => $csv,
            'filename' => 'leads-'.now()->format('Y-m-d').'.csv',
            'count' => $leads->count(),
            'stats' => $this->stats($exhibitorId),
        ]]);
    }

    /**
     * Team Connections: every connection the booth's team has made, rolled up
     * per teammate. Gives the booth owner one place to see who is capturing,
     * how those relationships are progressing, and where two reps are working
     * the same company.
     */
    public function team(Request $request): JsonResponse
    {
        $exhibitorId = $request->attributes->get('exhibitor_id');

        $members = ExhibitorMember::with('contact')
            ->where('exhibitor_id', $exhibitorId)
            ->orderBy('id')
            ->get();

        // A booth's lead list is small (hundreds at most), so roll up in memory
        // rather than firing a query per teammate.
        $leads = ExhibitorLead::where('exhibitor_id', $exhibitorId)
            ->get(['id', 'company', 'rating', 'status', 'source', 'scanned_by_member_id', 'scanned_at', 'created_at']);

        // Group only the attributed leads — groupBy would fold a null rep into
        // the '' key, which is easy to read back as "member 0".
        $byMember = $leads->whereNotNull('scanned_by_member_id')->groupBy('scanned_by_member_id');
        $total = $leads->count();

        $rows = $members
            ->map(fn (ExhibitorMember $m) => $this->connectionRow(
                $byMember->get($m->id, collect()),
                $total,
                [
                    'member_id' => $m->id,
                    'name' => $this->memberName($m),
                    'email' => $m->contact?->email,
                    'role' => $m->role,
                    'is_lead_capturer' => (bool) $m->is_lead_capturer,
                ],
            ))
            ->sortByDesc('total')
            ->values();

        // Leads captured at the booth without a rep attached — these are the
        // ones that need an owner before anybody follows up.
        $unassigned = $this->connectionRow($leads->whereNull('scanned_by_member_id'), $total, [
            'member_id' => null,
            'name' => 'Unassigned',
            'email' => null,
            'role' => null,
            'is_lead_capturer' => false,
        ]);

        return response()->json([
            'data' => $rows,
            'unassigned' => $unassigned,
            'totals' => $this->teamTotals($leads, $members, $rows),
            'timeline' => $this->timeline($leads),
            'overlaps' => $this->overlaps($leads, $members),
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** One teammate's (or the unassigned bucket's) connection roll-up. */
    private function connectionRow($leads, int $teamTotal, array $identity): array
    {
        $count = $leads->count();
        $won = $leads->where('status', 'won')->count();
        $last = $leads->map(fn ($l) => $l->scanned_at ?? $l->created_at)->filter()->max();

        return $identity + [
            'total' => $count,
            'hot' => $leads->where('rating', 'hot')->count(),
            'warm' => $leads->where('rating', 'warm')->count(),
            'cold' => $leads->where('rating', 'cold')->count(),
            'pending' => $leads->where('status', 'pending')->count(),
            'contacted' => $leads->where('status', '!=', 'pending')->count(),
            'qualified' => $leads->whereIn('status', ['qualified', 'won'])->count(),
            'won' => $won,
            'lost' => $leads->where('status', 'lost')->count(),
            'companies' => $this->distinctCompanies($leads),
            'scanned' => $leads->where('source', 'scan')->count(),
            // Win rate over this rep's own connections, and their share of the
            // booth's whole book.
            'conversion_rate' => $count ? (int) round(($won / $count) * 100) : 0,
            'share' => $teamTotal ? (int) round(($count / $teamTotal) * 100) : 0,
            'last_connection_at' => optional($last)->toIso8601String(),
        ];
    }

    private function teamTotals($leads, $members, $rows): array
    {
        $total = $leads->count();
        $won = $leads->where('status', 'won')->count();
        $today = $leads->filter(fn ($l) => ($l->scanned_at ?? $l->created_at)?->isToday())->count();

        return [
            'connections' => $total,
            'members' => $members->count(),
            // Teammates who actually brought something back.
            'active_members' => $rows->where('total', '>', 0)->count(),
            'hot' => $leads->where('rating', 'hot')->count(),
            'contacted' => $leads->where('status', '!=', 'pending')->count(),
            'won' => $won,
            'unassigned' => $leads->whereNull('scanned_by_member_id')->count(),
            'companies' => $this->distinctCompanies($leads),
            'today' => $today,
            'conversion_rate' => $total ? (int) round(($won / $total) * 100) : 0,
            'avg_per_member' => $members->count() ? round($total / $members->count(), 1) : 0,
        ];
    }

    /** Connections per day for the last 7 days (oldest first), for the trend bar. */
    private function timeline($leads): array
    {
        $days = collect(range(6, 0))->map(fn ($back) => now()->subDays($back)->startOfDay());
        $counts = $leads
            ->groupBy(fn ($l) => optional($l->scanned_at ?? $l->created_at)->toDateString())
            ->map->count();

        return $days->map(fn ($d) => [
            'date' => $d->toDateString(),
            'label' => $d->format('D'),
            'count' => (int) ($counts[$d->toDateString()] ?? 0),
        ])->all();
    }

    /**
     * Companies more than one teammate has connected with — the booth's
     * duplicate-effort (or multi-threaded account) signal.
     */
    private function overlaps($leads, $members): array
    {
        $names = $members->mapWithKeys(fn (ExhibitorMember $m) => [$m->id => $this->memberName($m)]);

        return $leads
            ->filter(fn ($l) => filled($l->company) && $l->scanned_by_member_id)
            ->groupBy(fn ($l) => mb_strtolower(trim($l->company)))
            ->filter(fn ($group) => $group->pluck('scanned_by_member_id')->unique()->count() > 1)
            ->map(fn ($group) => [
                'company' => trim($group->first()->company),
                'leads' => $group->count(),
                'members' => $group->pluck('scanned_by_member_id')->unique()
                    ->map(fn ($id) => $names[$id] ?? 'Unknown')->values()->all(),
            ])
            ->sortByDesc('leads')
            ->values()
            ->take(8)
            ->all();
    }

    private function distinctCompanies($leads): int
    {
        return $leads->filter(fn ($l) => filled($l->company))
            ->map(fn ($l) => mb_strtolower(trim($l->company)))
            ->unique()
            ->count();
    }

    private function memberName(ExhibitorMember $member): string
    {
        $contact = $member->contact;

        return $contact
            ? (trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')) ?: (string) $contact->email)
            : 'Teammate #'.$member->id;
    }

    private function stats(int $exhibitorId): array
    {
        $counts = ExhibitorLead::where('exhibitor_id', $exhibitorId)
            ->selectRaw('rating, status, exported_at')
            ->get();

        $total = $counts->count();

        return [
            'total' => $total,
            'hot' => $counts->where('rating', 'hot')->count(),
            'warm' => $counts->where('rating', 'warm')->count(),
            'cold' => $counts->where('rating', 'cold')->count(),
            // "Contacted" = anything past the initial pending state.
            'contacted' => $counts->where('status', '!=', 'pending')->count(),
            'recently_exported' => $counts->filter(fn ($l) => $l->exported_at && $l->exported_at->gt(now()->subDays(7)))->count(),
        ];
    }

    private function applySort($query, string $sort): void
    {
        match ($sort) {
            'name' => $query->orderBy('name'),
            'company' => $query->orderBy('company'),
            // Hottest first when sorting by rating.
            'rating' => $query->orderByRaw("array_position(ARRAY['hot','warm','cold'], rating)"),
            'oldest' => $query->orderBy('id'),
            default => $query->orderByDesc('id'),
        };
    }

    private function validated(Request $request, bool $creating): array
    {
        // Reassignment must stay inside the booth: scope the exists() to this
        // exhibitor's own team so a lead can't be handed to another booth.
        $ownMember = Rule::exists('exhibitor_members', 'id')
            ->where('exhibitor_id', $request->attributes->get('exhibitor_id'))
            ->whereNull('deleted_at');

        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:180'],
            'email' => ['sometimes', 'nullable', 'email', 'max:180'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'company' => ['sometimes', 'nullable', 'string', 'max:180'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:180'],
            'rating' => ['sometimes', Rule::in(ExhibitorLead::RATINGS)],
            'status' => ['sometimes', Rule::in(ExhibitorLead::STATUSES)],
            'source' => ['sometimes', Rule::in(['scan', 'manual', 'connect', 'import'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'scanned_by_member_id' => ['sometimes', 'nullable', 'integer', $ownMember],
        ]);
    }

    private function find(Request $request, string $uuid): ExhibitorLead
    {
        return ExhibitorLead::where('exhibitor_id', $request->attributes->get('exhibitor_id'))
            ->where('uuid', $uuid)
            ->firstOrFail();
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
