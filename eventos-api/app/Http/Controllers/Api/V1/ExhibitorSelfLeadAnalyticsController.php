<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\NamesExhibitorMembers;
use App\Http\Controllers\Controller;
use App\Models\ExhibitorLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Leads Analytics — what the booth's capture actually produced.
 *
 * Team Connections answers "who captured what". This answers the questions the
 * stand manager asks on the last morning: is the pipeline converting, which
 * capture method is worth the staff time, when is the stand actually busy, and
 * which accounts are showing up more than once.
 *
 * Everything is derived from exhibitor_leads at read time — no snapshots, no
 * nightly job. A booth's lead list is hundreds of rows at most, so the whole
 * report is one query plus in-memory roll-ups.
 */
class ExhibitorSelfLeadAnalyticsController extends Controller
{
    use NamesExhibitorMembers;

    private const RANGES = [7, 30, 90];

    private const TOP_COMPANIES = 8;

    public function index(Request $request): JsonResponse
    {
        $exhibitorId = (int) $request->attributes->get('exhibitor_id');

        $params = $request->validate([
            'days' => ['nullable', 'integer', 'in:'.implode(',', self::RANGES)],
        ]);

        $days = (int) ($params['days'] ?? 30);
        $since = now()->subDays($days - 1)->startOfDay();

        $all = ExhibitorLead::where('exhibitor_id', $exhibitorId)->get();
        // `scanned_at` is when the badge was actually captured; created_at only
        // stands in for rows imported after the fact.
        $all->each(fn (ExhibitorLead $l) => $l->captured_at = $l->scanned_at ?? $l->created_at);

        $range = $all->filter(fn (ExhibitorLead $l) => $l->captured_at && $l->captured_at->gte($since));

        return response()->json([
            'data' => [
                'range' => ['days' => $days, 'from' => $since->toDateString(), 'to' => now()->toDateString()],
                'totals' => $this->totals($all, $range, $days),
                'timeline' => $this->timeline($range, $days),
                'funnel' => $this->funnel($all),
                'sources' => $this->breakdown($all, 'source', ['scan' => 'Badge scan', 'manual' => 'Added by hand', 'connect' => 'Connected in app', 'import' => 'Imported']),
                'ratings' => $this->breakdown($all, 'rating', ['hot' => 'Hot', 'warm' => 'Warm', 'cold' => 'Cold']),
                'hours' => $this->hours($all),
                'companies' => $this->companies($all),
                'reps' => $this->reps($all, $exhibitorId),
                'qualification' => $this->qualification($all),
            ],
        ]);
    }

    // ── Sections ────────────────────────────────────────────────────────────

    private function totals(Collection $all, Collection $range, int $days): array
    {
        $total = $all->count();
        $won = $all->where('status', 'won')->count();
        $closed = $all->whereIn('status', ['won', 'lost'])->count();

        return [
            'total' => $total,
            'in_range' => $range->count(),
            'per_day' => $days ? round($range->count() / $days, 1) : 0,
            'today' => $all->filter(fn (ExhibitorLead $l) => $l->captured_at?->isToday())->count(),
            'hot' => $all->where('rating', 'hot')->count(),
            'companies' => $this->distinctCompanies($all),
            'contacted' => $all->where('status', '!=', 'pending')->count(),
            'qualified' => $all->whereIn('status', ['qualified', 'won'])->count(),
            'won' => $won,
            'lost' => $all->where('status', 'lost')->count(),
            // Two different rates, because they answer different questions:
            // conversion over everything captured, win rate over what closed.
            'conversion_rate' => $total ? (int) round(($won / $total) * 100) : 0,
            'win_rate' => $closed ? (int) round(($won / $closed) * 100) : 0,
            'exported' => $all->whereNotNull('exported_at')->count(),
            'best_day' => $this->bestDay($all),
        ];
    }

    /** Daily capture counts across the window, oldest first. */
    private function timeline(Collection $range, int $days): array
    {
        $byDay = $range->groupBy(fn (ExhibitorLead $l) => $l->captured_at->toDateString());

        // Long windows are bucketed by week so the chart stays readable.
        $step = $days > 30 ? 7 : 1;
        $buckets = [];

        for ($back = $days - 1; $back >= 0; $back -= $step) {
            $start = now()->subDays($back)->startOfDay();
            $end = $start->copy()->addDays($step - 1);

            $count = 0;
            $hot = 0;
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $group = $byDay->get($d->toDateString(), collect());
                $count += $group->count();
                $hot += $group->where('rating', 'hot')->count();
            }

            $buckets[] = [
                'date' => $start->toDateString(),
                'label' => $step === 1 ? $start->format('j M') : $start->format('j M').'–'.$end->format('j M'),
                'count' => $count,
                'hot' => $hot,
            ];
        }

        return $buckets;
    }

    /**
     * Captured → contacted → qualified → won, each stage counting everything
     * that reached it *or beyond* (a won lead was also once contacted).
     */
    private function funnel(Collection $all): array
    {
        $total = $all->count();

        $stages = [
            ['key' => 'captured', 'label' => 'Captured', 'count' => $total],
            ['key' => 'contacted', 'label' => 'Followed up', 'count' => $all->whereNotIn('status', ['pending'])->count()],
            ['key' => 'qualified', 'label' => 'Qualified', 'count' => $all->whereIn('status', ['qualified', 'won'])->count()],
            ['key' => 'won', 'label' => 'Won', 'count' => $all->where('status', 'won')->count()],
        ];

        $previous = null;

        return array_map(function (array $stage) use ($total, &$previous) {
            $stage['share'] = $total ? (int) round(($stage['count'] / $total) * 100) : 0;
            // Drop-off is measured against the stage before it, which is the
            // number a rep can actually do something about.
            $stage['from_previous'] = $previous === null || $previous === 0
                ? 100
                : (int) round(($stage['count'] / $previous) * 100);
            $previous = $stage['count'];

            return $stage;
        }, $stages);
    }

    /** @param array<string, string> $labels */
    private function breakdown(Collection $all, string $field, array $labels): array
    {
        $total = $all->count();

        return collect($labels)->map(function (string $label, string $key) use ($all, $field, $total) {
            $group = $all->where($field, $key);

            return [
                'key' => $key,
                'label' => $label,
                'count' => $group->count(),
                'share' => $total ? (int) round(($group->count() / $total) * 100) : 0,
                'won' => $group->where('status', 'won')->count(),
            ];
        })->values()->filter(fn (array $row) => $row['count'] > 0)->values()->all();
    }

    /** Capture-by-hour, so the booth knows when to have its best people on. */
    private function hours(Collection $all): array
    {
        $byHour = $all->filter(fn (ExhibitorLead $l) => (bool) $l->captured_at)
            ->groupBy(fn (ExhibitorLead $l) => (int) $l->captured_at->format('G'))
            ->map->count();

        // Show the trading day; a 3am scan is a data-entry artefact, not footfall.
        return collect(range(8, 19))->map(fn (int $hour) => [
            'hour' => $hour,
            'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
            'count' => (int) ($byHour[$hour] ?? 0),
        ])->values()->all();
    }

    /** Accounts showing up more than once — where a real opportunity hides. */
    private function companies(Collection $all): array
    {
        return $all
            ->filter(fn (ExhibitorLead $l) => filled($l->company))
            ->groupBy(fn (ExhibitorLead $l) => mb_strtolower(trim($l->company)))
            ->map(fn (Collection $group) => [
                'company' => trim($group->first()->company),
                'leads' => $group->count(),
                'hot' => $group->where('rating', 'hot')->count(),
                'won' => $group->where('status', 'won')->count(),
            ])
            ->sortByDesc('leads')
            ->values()
            ->take(self::TOP_COMPANIES)
            ->all();
    }

    private function reps(Collection $all, int $exhibitorId): array
    {
        $names = $this->memberNames($exhibitorId);

        return $all
            ->whereNotNull('scanned_by_member_id')
            ->groupBy('scanned_by_member_id')
            ->map(function (Collection $group, $memberId) use ($names) {
                $won = $group->where('status', 'won')->count();

                return [
                    'member_id' => (int) $memberId,
                    'name' => $names->get((int) $memberId, 'Former teammate'),
                    'total' => $group->count(),
                    'hot' => $group->where('rating', 'hot')->count(),
                    'won' => $won,
                    'conversion_rate' => (int) round(($won / $group->count()) * 100),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * How much of the book has actually been worked through the BANT questions
     * (see ExhibitorSelfLeadQualificationController), and which question the
     * team most often cannot answer yes to.
     */
    private function qualification(Collection $all): array
    {
        $total = $all->count();
        $criteria = ExhibitorSelfLeadQualificationController::CRITERIA;

        $scores = $all->map(function (ExhibitorLead $l) use ($criteria) {
            $answers = $l->meta['qualification'] ?? [];

            return collect($criteria)->filter(fn (string $c) => (bool) ($answers[$c] ?? false))->count();
        });

        return [
            'scored' => $scores->filter(fn (int $met) => $met > 0)->count(),
            'unscored' => $scores->filter(fn (int $met) => $met === 0)->count(),
            'fully_qualified' => $scores->filter(fn (int $met) => $met === count($criteria))->count(),
            'coverage' => $total ? (int) round(($scores->filter(fn (int $met) => $met > 0)->count() / $total) * 100) : 0,
            'avg_score' => $total ? (int) round(($scores->avg() / count($criteria)) * 100) : 0,
            'criteria' => collect($criteria)->map(fn (string $c) => [
                'key' => $c,
                'label' => ucfirst($c),
                'count' => $all->filter(fn (ExhibitorLead $l) => (bool) ($l->meta['qualification'][$c] ?? false))->count(),
            ])->values()->all(),
        ];
    }

    // ── Small helpers ───────────────────────────────────────────────────────

    private function bestDay(Collection $all): ?array
    {
        $byDay = $all->filter(fn (ExhibitorLead $l) => (bool) $l->captured_at)
            ->groupBy(fn (ExhibitorLead $l) => $l->captured_at->toDateString())
            ->map->count()
            ->sortDesc();

        return $byDay->isEmpty() ? null : ['date' => $byDay->keys()->first(), 'count' => $byDay->first()];
    }

    private function distinctCompanies(Collection $all): int
    {
        return $all->filter(fn (ExhibitorLead $l) => filled($l->company))
            ->map(fn (ExhibitorLead $l) => mb_strtolower(trim($l->company)))
            ->unique()
            ->count();
    }
}
