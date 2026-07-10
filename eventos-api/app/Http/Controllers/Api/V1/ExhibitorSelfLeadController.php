<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExhibitorLeadResource;
use App\Models\Exhibitor;
use App\Models\ExhibitorLead;
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
            $query->where('scanned_by_member_id', (int) $rep);
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

    // ── Helpers ─────────────────────────────────────────────────────────────

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
            'scanned_by_member_id' => ['sometimes', 'nullable', 'integer', Rule::exists('exhibitor_members', 'id')],
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
