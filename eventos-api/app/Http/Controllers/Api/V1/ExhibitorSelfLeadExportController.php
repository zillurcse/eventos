<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\NamesExhibitorMembers;
use App\Http\Controllers\Controller;
use App\Models\Exhibitor;
use App\Models\ExhibitorLead;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Lead Export — take the book off the platform and into the CRM that will
 * actually work it.
 *
 * The toolbar export on All Leads dumps everything with a fixed column set.
 * This screen is the deliberate version: pick the columns your CRM expects,
 * narrow by rating / stage / owner / capture window, export only what has never
 * been exported before, and see exactly how many rows that is before you
 * download anything.
 *
 * Rows are stamped with exported_at on download, which is what makes the
 * "not exported yet" filter — the one that stops a rep importing the same 300
 * contacts twice — possible at all.
 */
class ExhibitorSelfLeadExportController extends Controller
{
    use NamesExhibitorMembers;

    /**
     * Every column the export can carry. `default` is the set a fresh CRM
     * import expects; the rest are opt-in.
     */
    private const COLUMNS = [
        'name' => ['label' => 'Full name', 'default' => true],
        'email' => ['label' => 'Email', 'default' => true],
        'phone' => ['label' => 'Phone', 'default' => true],
        'company' => ['label' => 'Company', 'default' => true],
        'job_title' => ['label' => 'Job title', 'default' => true],
        'rating' => ['label' => 'Rating', 'default' => true],
        'status' => ['label' => 'Pipeline stage', 'default' => true],
        'source' => ['label' => 'Capture method', 'default' => false],
        'owner' => ['label' => 'Owner', 'default' => true],
        'qualification_score' => ['label' => 'Qualification score', 'default' => false],
        'criteria' => ['label' => 'BANT answers', 'default' => false],
        'next_step' => ['label' => 'Next step', 'default' => false],
        'follow_up_at' => ['label' => 'Follow-up date', 'default' => false],
        'notes' => ['label' => 'Notes', 'default' => true],
        'captured_at' => ['label' => 'Captured at', 'default' => true],
        'exported_at' => ['label' => 'Last exported', 'default' => false],
    ];

    private const FORMATS = ['csv', 'excel', 'json'];

    private const SAMPLE_ROWS = 5;

    /** GET /exhibitor/leads/export/summary — what the current selection covers. */
    public function summary(Request $request): JsonResponse
    {
        $exhibitorId = (int) $request->attributes->get('exhibitor_id');
        $filters = $this->filters($request);

        $selected = $this->query($exhibitorId, $filters)->with('scannedBy.contact')->orderByDesc('id')->get();
        $all = ExhibitorLead::where('exhibitor_id', $exhibitorId)->get(['exported_at']);
        $names = $this->memberNames($exhibitorId);

        return response()->json([
            'data' => [
                'matched' => $selected->count(),
                'sample' => $selected->take(self::SAMPLE_ROWS)
                    ->map(fn (ExhibitorLead $l) => $this->row($l, array_keys(self::COLUMNS), $names))
                    ->values()->all(),
                'coverage' => [
                    'total' => $all->count(),
                    'never_exported' => $all->whereNull('exported_at')->count(),
                    'exported' => $all->whereNotNull('exported_at')->count(),
                    'recent' => $all->filter(fn ($l) => $l->exported_at && $l->exported_at->gt(now()->subDays(7)))->count(),
                    'last_export_at' => optional($all->max('exported_at'))->toIso8601String(),
                ],
            ],
            'columns' => collect(self::COLUMNS)
                ->map(fn (array $c, string $key) => ['key' => $key, 'label' => $c['label'], 'default' => $c['default']])
                ->values()->all(),
            'team' => $this->teamOptions($exhibitorId),
            'formats' => self::FORMATS,
        ]);
    }

    /** POST /exhibitor/leads/export/download — build the file. */
    public function download(Request $request): JsonResponse
    {
        $exhibitorId = (int) $request->attributes->get('exhibitor_id');

        $data = $request->validate([
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => [Rule::in(array_keys(self::COLUMNS))],
            'format' => ['required', Rule::in(self::FORMATS)],
            // Off by default: a dry run for a CRM mapping should not burn the
            // "never exported" flag the next real export depends on.
            'mark_exported' => ['nullable', 'boolean'],
        ]);

        $filters = $this->filters($request);
        $columns = array_values(array_intersect(array_keys(self::COLUMNS), $data['columns']));

        $leads = $this->query($exhibitorId, $filters)->with('scannedBy.contact')->orderByDesc('id')->get();
        $names = $this->memberNames($exhibitorId);
        $rows = $leads->map(fn (ExhibitorLead $l) => $this->row($l, $columns, $names));

        $exhibitor = Exhibitor::find($exhibitorId);
        $slug = Str::slug($exhibitor?->name ?: 'exhibitor');
        $extension = $data['format'] === 'json' ? 'json' : 'csv';

        $content = $data['format'] === 'json'
            ? json_encode($rows->values(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : $this->toCsv($columns, $rows, excel: $data['format'] === 'excel');

        if ($request->boolean('mark_exported') && $leads->isNotEmpty()) {
            ExhibitorLead::whereKey($leads->pluck('id'))->update(['exported_at' => now()]);
        }

        return response()->json(['data' => [
            'content' => $content,
            'filename' => "{$slug}-leads-".now()->format('Y-m-d-Hi').".{$extension}",
            'mime' => $data['format'] === 'json' ? 'application/json' : 'text/csv',
            'count' => $leads->count(),
        ]]);
    }

    // ── Selection ───────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'rating' => ['nullable', Rule::in(ExhibitorLead::RATINGS)],
            'status' => ['nullable', Rule::in(ExhibitorLead::STATUSES)],
            'source' => ['nullable', Rule::in(['scan', 'manual', 'connect', 'import'])],
            'rep' => ['nullable', 'string', 'max:20'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'only_new' => ['nullable', 'boolean'],
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function query(int $exhibitorId, array $filters): Builder
    {
        $query = ExhibitorLead::where('exhibitor_id', $exhibitorId);

        if ($term = trim((string) ($filters['search'] ?? ''))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)
                ->orWhere('company', 'ilike', $like));
        }
        foreach (['rating', 'status', 'source'] as $field) {
            if ($value = $filters[$field] ?? null) {
                $query->where($field, $value);
            }
        }
        if ($rep = $filters['rep'] ?? null) {
            $rep === 'unassigned'
                ? $query->whereNull('scanned_by_member_id')
                : $query->where('scanned_by_member_id', (int) $rep);
        }
        // Capture window, against the capture time rather than row creation —
        // an imported lead belongs to the day it was collected.
        if ($from = $filters['from'] ?? null) {
            $query->whereRaw('coalesce(scanned_at, created_at) >= ?', [Carbon::parse($from)->startOfDay()]);
        }
        if ($to = $filters['to'] ?? null) {
            $query->whereRaw('coalesce(scanned_at, created_at) <= ?', [Carbon::parse($to)->endOfDay()]);
        }
        if ($filters['only_new'] ?? false) {
            $query->whereNull('exported_at');
        }

        return $query;
    }

    // ── Rendering ───────────────────────────────────────────────────────────

    /**
     * @param  array<int, string>  $columns
     * @return array<string, string|null>
     */
    private function row(ExhibitorLead $lead, array $columns, Collection $names): array
    {
        $qualification = $lead->meta['qualification'] ?? [];
        $criteria = ExhibitorSelfLeadQualificationController::CRITERIA;
        $met = collect($criteria)->filter(fn (string $c) => (bool) ($qualification[$c] ?? false));

        $values = [
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'company' => $lead->company,
            'job_title' => $lead->job_title,
            'rating' => $lead->rating,
            'status' => $lead->status,
            'source' => $lead->source,
            'owner' => $lead->scanned_by_member_id ? $names->get($lead->scanned_by_member_id) : null,
            'qualification_score' => (string) (int) round(($met->count() / count($criteria)) * 100),
            // Readable in a spreadsheet cell: "Budget, Need" beats "1,0,1,0".
            'criteria' => $met->map(fn (string $c) => ucfirst($c))->implode(', '),
            'next_step' => $qualification['next_step'] ?? null,
            'follow_up_at' => $qualification['follow_up_at'] ?? null,
            'notes' => $lead->notes,
            'captured_at' => optional($lead->scanned_at ?? $lead->created_at)->toDateTimeString(),
            'exported_at' => optional($lead->exported_at)->toDateTimeString(),
        ];

        return collect($columns)->mapWithKeys(fn (string $key) => [$key => $values[$key] ?? null])->all();
    }

    /**
     * @param  array<int, string>  $columns
     * @param  Collection<int, array>  $rows
     */
    private function toCsv(array $columns, Collection $rows, bool $excel): string
    {
        $escape = fn ($v) => '"'.str_replace('"', '""', (string) $v).'"';

        $lines = [implode(',', array_map(fn (string $key) => $escape(self::COLUMNS[$key]['label']), $columns))];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map($escape, array_values($row)));
        }

        $csv = implode("\r\n", $lines);

        // Excel reads a UTF-8 CSV as the local codepage unless it finds a BOM,
        // which is how exported names come back mangled.
        return $excel ? "\u{FEFF}".$csv : $csv;
    }
}
