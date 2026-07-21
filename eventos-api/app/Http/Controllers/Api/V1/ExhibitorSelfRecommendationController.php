<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\NewExhibitorMessage;
use App\Http\Controllers\Controller;
use App\Models\Exhibitor;
use App\Models\ExhibitorConversation;
use App\Models\ExhibitorLead;
use App\Models\ExhibitorLeadSuggestion;
use App\Models\ExhibitorMember;
use App\Models\Participation;
use App\Services\Exhibitors\LeadRecommendationService;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Recommended Leads — "discover participants genuinely interested in your
 * company". The booth is shown attendees ranked by what they actually did
 * (LeadRecommendationService), can review that interaction history, route a
 * prospect to a teammate, and send a connection request that lands in the
 * attendee's own app as a real message.
 *
 * Acting on a suggestion is not a bookmark: assigning or connecting promotes
 * the attendee into the booth's CRM (exhibitor_leads) so it shows up in All
 * Leads and Team Connections like every other prospect. The suggestion row only
 * remembers the routing decision.
 */
class ExhibitorSelfRecommendationController extends Controller
{
    /** Signal keys the list can be filtered by. */
    private const SIGNALS = ['meeting', 'message', 'bookmark', 'visit', 'fit'];

    public function __construct(private readonly LeadRecommendationService $recommendations) {}

    /** GET /exhibitor/leads/recommended — the ranked discovery queue. */
    public function index(Request $request): JsonResponse
    {
        $exhibitor = $this->exhibitor($request);

        $params = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'signal' => ['nullable', Rule::in(self::SIGNALS)],
            'temperature' => ['nullable', Rule::in(['hot', 'warm', 'cold'])],
            // open = still to act on; the rest mirror the row's state chip.
            'state' => ['nullable', Rule::in(['open', 'assigned', 'requested', 'dismissed', 'all'])],
            'sort' => ['nullable', Rule::in(['score', 'recent', 'name'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $rows = $this->rows($exhibitor);
        $stats = $this->stats($rows);

        $filtered = $this->filter($rows, $params);
        $filtered = $this->sort($filtered, $params['sort'] ?? 'score');

        $perPage = (int) ($params['per_page'] ?? 10);
        $page = (int) ($params['page'] ?? 1);
        $total = $filtered->count();
        $slice = $filtered->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $slice->all(),
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total ? (($page - 1) * $perPage) + 1 : 0,
                'to' => $total ? min($page * $perPage, $total) : 0,
            ],
            'stats' => $stats,
            'team' => $this->team($exhibitor),
        ]);
    }

    /** GET /exhibitor/leads/recommended/{participation} — one prospect in full. */
    public function show(Request $request, string $participation): JsonResponse
    {
        $exhibitor = $this->exhibitor($request);
        $target = $this->participation($exhibitor, $participation);

        $row = $this->rows($exhibitor)->firstWhere('id', $target->uuid);
        abort_if(! $row, 404, 'This attendee is no longer recommended.');

        $profile = $target->profile_data ?? [];

        return response()->json(['data' => array_merge($row, [
            'bio' => $profile['bio'] ?? null,
            'country' => $profile['country'] ?? null,
            'city' => $profile['city'] ?? null,
            'interests' => array_values((array) ($profile['interests'] ?? [])),
            'looking_for' => array_values((array) ($profile['looking_for'] ?? [])),
            'purpose_of_visit' => $profile['purpose_of_visit'] ?? null,
            'timeline' => $this->recommendations->timeline($exhibitor, $target),
        ])]);
    }

    /**
     * POST /exhibitor/leads/recommended/{participation}/assign — route the
     * prospect to a teammate (or back to the booth by sending member_id null),
     * promoting it into the CRM so the owner can work it.
     */
    public function assign(Request $request, string $participation): JsonResponse
    {
        $exhibitor = $this->exhibitor($request);
        $target = $this->participation($exhibitor, $participation);

        $data = $request->validate([
            'member_id' => ['present', 'nullable', 'integer', $this->ownMemberRule($exhibitor)],
        ]);

        $memberId = $data['member_id'] ? (int) $data['member_id'] : null;

        $suggestion = $this->suggestion($exhibitor, $target);
        $suggestion->fill([
            'assigned_member_id' => $memberId,
            // Assigning something that was written off un-dismisses it: the
            // team just told us it is worth working after all.
            'status' => $suggestion->requested_at ? 'requested' : ($memberId ? 'assigned' : 'new'),
            'dismissed_at' => null,
            'dismiss_reason' => null,
        ])->save();

        $this->promote($exhibitor, $target, $memberId);

        return response()->json(['data' => $this->refresh($exhibitor, $target)]);
    }

    /**
     * POST /exhibitor/leads/recommended/{participation}/connect — send the
     * connection request. It is delivered as a real message in the attendee's
     * exhibitor inbox (plus a notification), so a reply comes straight back to
     * the booth's Contact inbox.
     */
    public function connect(Request $request, string $participation, NotificationService $notifications): JsonResponse
    {
        $exhibitor = $this->exhibitor($request);
        $target = $this->participation($exhibitor, $participation);
        $actingMemberId = (int) $request->attributes->get('exhibitor_member_id');

        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'member_id' => ['nullable', 'integer', $this->ownMemberRule($exhibitor)],
        ]);

        $suggestion = $this->suggestion($exhibitor, $target);

        // Re-sending is allowed (a follow-up nudge is normal), but only once a
        // day: the booth should not be able to spam a badge.
        abort_if(
            $suggestion->requested_at && $suggestion->requested_at->gt(now()->subDay()),
            422,
            'A connection request was already sent to this attendee today.',
        );

        $convo = ExhibitorConversation::firstOrCreate(
            [
                'event_id' => $exhibitor->event_id,
                'exhibitor_id' => $exhibitor->id,
                'participation_id' => $target->id,
            ],
            ['organization_id' => $exhibitor->organization_id],
        );

        $message = $convo->messages()->create([
            'organization_id' => $exhibitor->organization_id,
            'event_id' => $exhibitor->event_id,
            'sender_side' => 'exhibitor',
            'sender_member_id' => $actingMemberId ?: null,
            'body' => $data['message'],
            'meta' => ['connection_request' => true],
        ]);

        $convo->update(['last_message_at' => now()]);

        broadcast(new NewExhibitorMessage($message, $convo->uuid, $exhibitor->uuid, $target->id));

        $notifications->notify(
            'participation', $target->id, $exhibitor->organization_id, $exhibitor->event_id,
            'exhibitor.connection_request',
            [
                'title' => 'Connection request',
                'body' => $exhibitor->name.' would like to connect with you.',
                'exhibitor_id' => $exhibitor->uuid,
                'conversation_id' => $convo->uuid,
            ],
        );

        $memberId = isset($data['member_id']) && $data['member_id']
            ? (int) $data['member_id']
            : ($suggestion->assigned_member_id ?: ($actingMemberId ?: null));

        $suggestion->fill([
            'status' => 'requested',
            'requested_at' => now(),
            'requested_by_member_id' => $actingMemberId ?: null,
            'assigned_member_id' => $memberId,
            'dismissed_at' => null,
            'dismiss_reason' => null,
        ])->save();

        // We have now made contact, so the CRM row reflects that rather than
        // sitting at "pending" behind the rep's back.
        $this->promote($exhibitor, $target, $memberId, 'contacted');

        return response()->json(['data' => $this->refresh($exhibitor, $target)], 201);
    }

    /** POST /exhibitor/leads/recommended/{participation}/dismiss — not for us. */
    public function dismiss(Request $request, string $participation): JsonResponse
    {
        $exhibitor = $this->exhibitor($request);
        $target = $this->participation($exhibitor, $participation);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        $this->suggestion($exhibitor, $target)->fill([
            'status' => 'dismissed',
            'dismissed_at' => now(),
            'dismiss_reason' => $data['reason'] ?? null,
        ])->save();

        return response()->json(['data' => $this->refresh($exhibitor, $target)]);
    }

    /** DELETE /exhibitor/leads/recommended/{participation}/dismiss — undo. */
    public function restore(Request $request, string $participation): JsonResponse
    {
        $exhibitor = $this->exhibitor($request);
        $target = $this->participation($exhibitor, $participation);

        $suggestion = $this->suggestion($exhibitor, $target);
        $suggestion->fill([
            'status' => $suggestion->requested_at ? 'requested' : ($suggestion->assigned_member_id ? 'assigned' : 'new'),
            'dismissed_at' => null,
            'dismiss_reason' => null,
        ])->save();

        return response()->json(['data' => $this->refresh($exhibitor, $target)]);
    }

    // ── Row assembly ────────────────────────────────────────────────────────

    /** Every scored candidate, merged with what the team has already decided. */
    private function rows(Exhibitor $exhibitor): Collection
    {
        $scored = $this->recommendations->discover($exhibitor);

        if ($scored->isEmpty()) {
            return collect();
        }

        $ids = $scored->keys()->all();

        $suggestions = ExhibitorLeadSuggestion::where('exhibitor_id', $exhibitor->id)
            ->whereIn('participation_id', $ids)
            ->get()
            ->keyBy('participation_id');

        $leads = ExhibitorLead::where('exhibitor_id', $exhibitor->id)
            ->whereIn('participation_id', $ids)
            ->get()
            ->keyBy('participation_id');

        $names = $this->memberNames($exhibitor);

        return $scored
            ->map(fn (array $row) => $this->format($row, $suggestions->get($row['participation']->id), $leads->get($row['participation']->id), $names))
            ->values();
    }

    private function format(array $row, ?ExhibitorLeadSuggestion $suggestion, ?ExhibitorLead $lead, Collection $names): array
    {
        /** @var Participation $p */
        $p = $row['participation'];
        $contact = $p->contact;
        $profile = $p->profile_data ?? [];

        // The attendee wrote to us, or we already own them as a lead — either
        // way the booth has their details legitimately. Everyone else stays a
        // profile card until they accept a connection request.
        $disclosed = ($row['signals']['messages'] ?? 0) > 0
            || ($row['signals']['meetings'] ?? 0) > 0
            || $lead !== null;

        $requestedAt = $suggestion?->requested_at;
        $lastSignal = $row['last_signal_at'];

        return [
            'id' => $p->uuid,
            'name' => trim(($contact?->first_name ?? '').' '.($contact?->last_name ?? '')) ?: 'Attendee',
            'job_title' => $contact?->job_title ?: ($profile['job_title'] ?? $profile['designation'] ?? null),
            'company' => $contact?->company ?: ($profile['company'] ?? null),
            'avatar_url' => $profile['avatar_url'] ?? ($profile['image_url'] ?? null),
            'email' => $disclosed ? $contact?->email : null,
            'phone' => $disclosed ? ($contact?->phone ?: ($profile['phone'] ?? null)) : null,
            'contact_locked' => ! $disclosed,

            'score' => $row['score'],
            'temperature' => $row['temperature'],
            'reasons' => $row['reasons'],
            'signals' => $row['signals'],
            'last_message' => $row['last_message'],
            'last_signal_at' => $lastSignal?->toIso8601String(),

            'state' => $suggestion?->dismissed_at ? 'dismissed' : ($requestedAt ? 'requested' : ($suggestion?->assigned_member_id ? 'assigned' : 'new')),
            'assigned_member_id' => $suggestion?->assigned_member_id,
            'assigned_to' => $suggestion?->assigned_member_id ? $names->get($suggestion->assigned_member_id) : null,
            'requested_at' => $requestedAt?->toIso8601String(),
            'dismiss_reason' => $suggestion?->dismiss_reason,
            // They wrote back after we reached out — the request worked.
            'responded' => (bool) ($requestedAt && $lastSignal && $lastSignal->gt($requestedAt)),

            'lead' => $lead ? [
                'id' => $lead->uuid,
                'status' => $lead->status,
                'rating' => $lead->rating,
            ] : null,
        ];
    }

    /** @param array<string, mixed> $params */
    private function filter(Collection $rows, array $params): Collection
    {
        $state = $params['state'] ?? 'open';

        $rows = match ($state) {
            'all' => $rows,
            'open' => $rows->where('state', '!=', 'dismissed'),
            default => $rows->where('state', $state),
        };

        if ($signal = $params['signal'] ?? null) {
            $rows = $rows->filter(fn (array $r) => collect($r['reasons'])->contains('key', $signal));
        }

        if ($temperature = $params['temperature'] ?? null) {
            $rows = $rows->where('temperature', $temperature);
        }

        if ($term = trim((string) ($params['search'] ?? ''))) {
            $needle = mb_strtolower($term);
            $rows = $rows->filter(fn (array $r) => str_contains(mb_strtolower(
                $r['name'].' '.$r['company'].' '.$r['job_title'],
            ), $needle));
        }

        return $rows->values();
    }

    private function sort(Collection $rows, string $sort): Collection
    {
        return match ($sort) {
            'recent' => $rows->sortByDesc(fn (array $r) => $r['last_signal_at'] ?? '')->values(),
            'name' => $rows->sortBy(fn (array $r) => mb_strtolower($r['name']))->values(),
            default => $rows->sortByDesc(fn (array $r) => $r['score'])->values(),
        };
    }

    private function stats(Collection $rows): array
    {
        $open = $rows->where('state', '!=', 'dismissed');

        return [
            'total' => $open->count(),
            'hot' => $open->where('temperature', 'hot')->count(),
            // The two signals that mean somebody reached out to *us*.
            'high_intent' => $open->filter(fn (array $r) => $r['signals']['meetings'] > 0 || $r['signals']['messages'] > 0)->count(),
            'requested' => $rows->where('state', 'requested')->count(),
            'responded' => $rows->where('responded', true)->count(),
            'assigned' => $rows->whereIn('state', ['assigned', 'requested'])->count(),
            'in_crm' => $rows->filter(fn (array $r) => $r['lead'] !== null)->count(),
            'dismissed' => $rows->where('state', 'dismissed')->count(),
            'active_today' => $open->filter(fn (array $r) => $r['last_signal_at'] && $r['last_signal_at'] >= now()->startOfDay()->toIso8601String())->count(),
        ];
    }

    // ── Actions ─────────────────────────────────────────────────────────────

    /**
     * Put the prospect in the CRM (or update the row that is already there).
     * Idempotent: exhibitor_leads is unique on (exhibitor_id, participation_id).
     */
    private function promote(Exhibitor $exhibitor, Participation $target, ?int $memberId, ?string $status = null): ExhibitorLead
    {
        $lead = ExhibitorLead::where('exhibitor_id', $exhibitor->id)
            ->where('participation_id', $target->id)
            ->first();

        $contact = $target->contact;
        $profile = $target->profile_data ?? [];

        if (! $lead) {
            $scored = $this->recommendations->discover($exhibitor)->get($target->id);

            $lead = new ExhibitorLead;
            $lead->forceFill([
                'organization_id' => $exhibitor->organization_id,
                'event_id' => $exhibitor->event_id,
                'exhibitor_id' => $exhibitor->id,
                'participation_id' => $target->id,
                'name' => trim(($contact?->first_name ?? '').' '.($contact?->last_name ?? '')) ?: 'Attendee',
                'email' => $contact?->email,
                'phone' => $contact?->phone ?: ($profile['phone'] ?? null),
                'company' => $contact?->company ?: ($profile['company'] ?? null),
                'job_title' => $contact?->job_title ?: ($profile['job_title'] ?? $profile['designation'] ?? null),
                // The interest score already said how warm this is; starting
                // every recommended lead at "cold" would throw that away.
                'rating' => $scored['temperature'] ?? 'warm',
                'source' => 'connect',
                'status' => 'pending',
                'scanned_at' => now(),
            ]);
        }

        // Re-assigning to nobody is a real instruction (back to the booth), so
        // the owner is always written; the status only ever moves forward.
        $lead->forceFill([
            'scanned_by_member_id' => $memberId,
            'status' => $status ?: ($lead->status ?: 'pending'),
        ])->save();

        return $lead;
    }

    private function suggestion(Exhibitor $exhibitor, Participation $target): ExhibitorLeadSuggestion
    {
        $suggestion = ExhibitorLeadSuggestion::firstOrNew([
            'exhibitor_id' => $exhibitor->id,
            'participation_id' => $target->id,
        ]);

        if (! $suggestion->exists) {
            $scored = $this->recommendations->discover($exhibitor)->get($target->id);

            $suggestion->fill([
                'organization_id' => $exhibitor->organization_id,
                'event_id' => $exhibitor->event_id,
                'score' => $scored['score'] ?? 0,
                'signals' => $scored['signals'] ?? null,
            ]);
        }

        return $suggestion;
    }

    /** The row as the client should now render it, after an action. */
    private function refresh(Exhibitor $exhibitor, Participation $target): ?array
    {
        return $this->rows($exhibitor)->firstWhere('id', $target->uuid);
    }

    // ── Lookups ─────────────────────────────────────────────────────────────

    private function exhibitor(Request $request): Exhibitor
    {
        return Exhibitor::findOrFail($request->attributes->get('exhibitor_id'));
    }

    private function participation(Exhibitor $exhibitor, string $uuid): Participation
    {
        return Participation::with('contact')
            ->where('event_id', $exhibitor->event_id)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /** Reassignment must stay inside this booth's own team. */
    private function ownMemberRule(Exhibitor $exhibitor): Exists
    {
        return Rule::exists('exhibitor_members', 'id')
            ->where('exhibitor_id', $exhibitor->id)
            ->whereNull('deleted_at');
    }

    private function team(Exhibitor $exhibitor): array
    {
        return ExhibitorMember::with('contact')
            ->where('exhibitor_id', $exhibitor->id)
            ->orderBy('id')
            ->get()
            ->map(fn (ExhibitorMember $m) => [
                'id' => $m->id,
                'name' => $this->memberName($m),
                'role' => $m->role,
            ])->values()->all();
    }

    private function memberNames(Exhibitor $exhibitor): Collection
    {
        return ExhibitorMember::with('contact')
            ->where('exhibitor_id', $exhibitor->id)
            ->get()
            ->mapWithKeys(fn (ExhibitorMember $m) => [$m->id => $this->memberName($m)]);
    }

    private function memberName(ExhibitorMember $member): string
    {
        $c = $member->contact;

        return $c
            ? (trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: (string) $c->email)
            : 'Teammate #'.$member->id;
    }
}
