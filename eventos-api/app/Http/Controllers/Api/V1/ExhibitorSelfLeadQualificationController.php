<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\NamesExhibitorMembers;
use App\Http\Controllers\Controller;
use App\Models\ExhibitorLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Lead Qualification — the booth's pipeline board.
 *
 * A captured lead is not yet a prospect. This screen is where the team answers
 * the four questions that decide whether it is worth a follow-up: is there a
 * Budget, are we talking to someone with Authority, is there a real Need, and
 * is there a Timeline. Four booleans, a next step and a follow-up date live in
 * exhibitor_leads.meta.qualification; the pipeline stage stays on the lead's
 * own `status` column so All Leads, Team Connections and the analytics all keep
 * reading one source of truth.
 *
 * The board deliberately does not auto-advance a lead when all four boxes are
 * ticked — it offers the move. A pipeline that promotes itself is a pipeline
 * nobody trusts at the end of the quarter.
 */
class ExhibitorSelfLeadQualificationController extends Controller
{
    use NamesExhibitorMembers;

    /** The BANT criteria, in the order a rep works through them. */
    public const CRITERIA = ['budget', 'authority', 'need', 'timeline'];

    /** Board columns, in pipeline order. Won/lost close the board. */
    private const STAGES = [
        'pending' => 'To qualify',
        'connected' => 'Connected',
        'contacted' => 'In conversation',
        'qualified' => 'Qualified',
        'won' => 'Won',
        'lost' => 'Lost',
    ];

    /** Cards per column — a booth working more than this needs a filter, not a longer page. */
    private const COLUMN_LIMIT = 60;

    /** GET /exhibitor/leads/pipeline — the board, its columns and its health. */
    public function index(Request $request): JsonResponse
    {
        $exhibitorId = (int) $request->attributes->get('exhibitor_id');

        $params = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'rating' => ['nullable', Rule::in(ExhibitorLead::RATINGS)],
            'rep' => ['nullable', 'string', 'max:20'],
            'source' => ['nullable', Rule::in(['scan', 'manual', 'connect', 'import'])],
            // Only the leads whose follow-up date has passed.
            'due' => ['nullable', 'boolean'],
        ]);

        $query = ExhibitorLead::with('scannedBy.contact')->where('exhibitor_id', $exhibitorId);

        if ($term = trim((string) ($params['search'] ?? ''))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)
                ->orWhere('company', 'ilike', $like));
        }
        if ($rating = $params['rating'] ?? null) {
            $query->where('rating', $rating);
        }
        if ($source = $params['source'] ?? null) {
            $query->where('source', $source);
        }
        if ($rep = $params['rep'] ?? null) {
            $rep === 'unassigned'
                ? $query->whereNull('scanned_by_member_id')
                : $query->where('scanned_by_member_id', (int) $rep);
        }

        $names = $this->memberNames($exhibitorId);
        $leads = $query->orderByDesc('id')->get()->map(fn (ExhibitorLead $l) => $this->card($l, $names));

        if ($params['due'] ?? false) {
            $leads = $leads->filter(fn (array $l) => $l['follow_up_due'])->values();
        }

        $byStatus = $leads->groupBy('status');

        $columns = collect(self::STAGES)->map(fn (string $label, string $status) => [
            'status' => $status,
            'label' => $label,
            'count' => $byStatus->get($status, collect())->count(),
            // Hottest and most-qualified first: the board should read as a
            // priority list top-to-bottom, not a capture log.
            'leads' => $byStatus->get($status, collect())
                ->sortByDesc(fn (array $l) => [$l['score'], $l['rating'] === 'hot' ? 2 : ($l['rating'] === 'warm' ? 1 : 0)])
                ->take(self::COLUMN_LIMIT)
                ->values()
                ->all(),
        ])->values()->all();

        return response()->json([
            'data' => $columns,
            'stats' => $this->stats($leads),
            'team' => $this->teamOptions($exhibitorId),
            'criteria' => self::CRITERIA,
        ]);
    }

    /**
     * PATCH /exhibitor/leads/{lead}/qualification — score the lead and, when
     * the rep says so, move it along the pipeline.
     */
    public function update(Request $request, string $lead): JsonResponse
    {
        $exhibitorId = (int) $request->attributes->get('exhibitor_id');
        $memberId = (int) $request->attributes->get('exhibitor_member_id');

        $model = ExhibitorLead::with('scannedBy.contact')
            ->where('exhibitor_id', $exhibitorId)
            ->where('uuid', $lead)
            ->firstOrFail();

        $data = $request->validate([
            'budget' => ['sometimes', 'boolean'],
            'authority' => ['sometimes', 'boolean'],
            'need' => ['sometimes', 'boolean'],
            'timeline' => ['sometimes', 'boolean'],
            'next_step' => ['sometimes', 'nullable', 'string', 'max:200'],
            'follow_up_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(array_keys(self::STAGES))],
            'rating' => ['sometimes', Rule::in(ExhibitorLead::RATINGS)],
        ]);

        $meta = $model->meta ?? [];
        $qualification = $meta['qualification'] ?? [];

        foreach (self::CRITERIA as $criterion) {
            if (array_key_exists($criterion, $data)) {
                $qualification[$criterion] = (bool) $data[$criterion];
            }
        }
        foreach (['next_step', 'follow_up_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $qualification[$field] = $data[$field] ?: null;
            }
        }

        $qualification['qualified_by_member_id'] = $memberId ?: null;
        $qualification['updated_at'] = now()->toIso8601String();

        $meta['qualification'] = $qualification;
        $model->meta = $meta;

        foreach (['status', 'rating', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $model->{$field} = $data[$field];
            }
        }

        $model->save();

        return response()->json(['data' => $this->card($model->fresh('scannedBy.contact'), $this->memberNames($exhibitorId))]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** One board card: the lead, its BANT answers and what they add up to. */
    private function card(ExhibitorLead $lead, Collection $names): array
    {
        $qualification = $lead->meta['qualification'] ?? [];
        $answers = collect(self::CRITERIA)
            ->mapWithKeys(fn (string $c) => [$c => (bool) ($qualification[$c] ?? false)]);

        $met = $answers->filter()->count();
        $score = (int) round(($met / count(self::CRITERIA)) * 100);
        $followUp = $qualification['follow_up_at'] ?? null;

        return [
            'id' => $lead->uuid,
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'company' => $lead->company,
            'job_title' => $lead->job_title,
            'rating' => $lead->rating,
            'status' => $lead->status,
            'source' => $lead->source,
            'notes' => $lead->notes,
            'owner' => $lead->scanned_by_member_id ? $names->get($lead->scanned_by_member_id) : null,
            'owner_id' => $lead->scanned_by_member_id,

            'criteria' => $answers->all(),
            'met' => $met,
            'score' => $score,
            'grade' => $this->grade($score),
            'next_step' => $qualification['next_step'] ?? null,
            'follow_up_at' => $followUp,
            'follow_up_due' => (bool) ($followUp && $followUp <= now()->toDateString()),
            'qualified_at' => $qualification['updated_at'] ?? null,
            // Everything checked but still sitting mid-pipeline: the one nudge
            // the board is allowed to give.
            'ready_to_advance' => $met === count(self::CRITERIA) && ! in_array($lead->status, ['qualified', 'won'], true),

            'created_at' => optional($lead->scanned_at ?? $lead->created_at)->toIso8601String(),
        ];
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 100 => 'qualified',
            $score >= 50 => 'developing',
            $score > 0 => 'early',
            default => 'unscored',
        };
    }

    private function stats(Collection $leads): array
    {
        $total = $leads->count();
        $scored = $leads->where('score', '>', 0);
        $open = $leads->whereNotIn('status', ['won', 'lost']);

        return [
            'total' => $total,
            // Nobody has answered a single question on these yet.
            'unscored' => $leads->where('score', 0)->count(),
            'in_progress' => $scored->where('score', '<', 100)->count(),
            'qualified' => $leads->where('score', 100)->count(),
            'ready_to_advance' => $leads->where('ready_to_advance', true)->count(),
            'follow_ups_due' => $leads->where('follow_up_due', true)->count(),
            'no_next_step' => $open->filter(fn (array $l) => blank($l['next_step']))->count(),
            'won' => $leads->where('status', 'won')->count(),
            'lost' => $leads->where('status', 'lost')->count(),
            'avg_score' => $total ? (int) round($leads->avg('score')) : 0,
            // Of everything that reached a decision, how much did we win.
            'win_rate' => $this->winRate($leads),
        ];
    }

    private function winRate(Collection $leads): int
    {
        $closed = $leads->whereIn('status', ['won', 'lost'])->count();

        return $closed ? (int) round(($leads->where('status', 'won')->count() / $closed) * 100) : 0;
    }
}
