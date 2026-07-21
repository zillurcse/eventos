<?php

namespace App\Services\Exhibitors;

use App\Models\CheckIn;
use App\Models\CheckInStation;
use App\Models\Exhibitor;
use App\Models\ExhibitorConversation;
use App\Models\ExhibitorMeetingRequest;
use App\Models\ExhibitorMember;
use App\Models\ExhibitorMessage;
use App\Models\ExhibitorProduct;
use App\Models\Participation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Scores event participants on how interested they look in one booth.
 *
 * The whole point of "Recommended Leads" is that the exhibitor should not have
 * to guess. Everything below is a thing the attendee *did*: asked for a
 * meeting, messaged the booth, saved it, walked up to it — plus a weaker
 * "matches what you sell" signal from their own stated interests. No signal, no
 * recommendation: an unranked list of every badge in the hall is what the
 * delegate directory is for.
 *
 * Scores are computed per request rather than stored. During a live event the
 * inputs change by the hour, and a booth acting on yesterday's ranking is worse
 * off than one acting on none.
 */
class LeadRecommendationService
{
    /**
     * Signal weights, on a 0–100 scale. The ordering encodes the sales reality:
     * asking for a meeting is worth far more than browsing, and anything the
     * attendee initiated outranks anything we inferred.
     */
    private const W_MEETING = 40;          // asked for a meeting

    private const W_MEETING_EXTRA = 10;    // …more than once

    private const W_MESSAGE = 25;          // opened a conversation

    private const W_MESSAGE_EXTRA = 5;     // per additional message (capped)

    private const W_MESSAGE_CAP = 40;

    private const W_BOOKMARK = 15;         // saved the booth in the app

    private const W_VISIT = 12;            // first booth scan

    private const W_VISIT_EXTRA = 6;       // per return visit (capped)

    private const W_VISIT_CAP = 30;

    private const W_FIT = 8;               // per matching interest keyword

    private const W_FIT_CAP = 16;

    private const W_FRESH = 8;             // any signal in the last 24h

    private const HOT = 70;

    private const WARM = 40;

    /** Profile-only matches are capped: they are a hint, not an intent signal. */
    private const FIT_ONLY_LIMIT = 150;

    /** Memoised per request — one screen asks for the ranking several times. */
    private array $cache = [];

    /**
     * Every scored candidate for this booth, best first.
     *
     * @return Collection<int, array> keyed by participation id
     */
    public function discover(Exhibitor $exhibitor): Collection
    {
        return $this->cache[$exhibitor->id] ??= $this->compute($exhibitor);
    }

    /** @return Collection<int, array> */
    private function compute(Exhibitor $exhibitor): Collection
    {
        $messages = $this->messageSignals($exhibitor);
        $meetings = $this->meetingSignals($exhibitor);
        $bookmarks = $this->bookmarkSignals($exhibitor);
        $visits = $this->visitSignals($exhibitor);

        $engagedIds = collect([$messages, $meetings, $bookmarks, $visits])
            ->flatMap(fn (Collection $c) => $c->keys())
            ->unique();

        $vocabulary = $this->vocabulary($exhibitor);
        $fitIds = $this->fitCandidates($exhibitor, $vocabulary, $engagedIds);

        // People who wrote to the booth themselves stay reachable even if they
        // opted out of the public directory — they opened the door.
        $inbound = $messages->keys()->merge($meetings->keys())->unique();

        $participations = $this->reachable($exhibitor, $engagedIds->merge($fitIds)->unique(), $inbound);

        return $participations
            ->map(function (Participation $p) use ($messages, $meetings, $bookmarks, $visits, $vocabulary) {
                $signals = [
                    'messages' => $messages->get($p->id),
                    'meeting' => $meetings->get($p->id),
                    'bookmarked' => $bookmarks->has($p->id),
                    'visits' => $visits->get($p->id),
                    'fit' => $this->fitMatches($p, $vocabulary),
                ];

                return $this->score($p, $signals);
            })
            // A candidate whose only "signal" was a keyword that no longer
            // matches scores nothing and is not a recommendation.
            ->filter(fn (array $row) => $row['score'] > 0)
            ->sortByDesc(fn (array $row) => [$row['score'], $row['last_signal_at']?->timestamp ?? 0])
            ->values()
            ->keyBy(fn (array $row) => $row['participation']->id);
    }

    /**
     * One candidate's interaction history, newest first — the "review their
     * interactions" panel. Kept separate from discover() so the list stays a
     * handful of grouped queries rather than a timeline per row.
     */
    public function timeline(Exhibitor $exhibitor, Participation $participation): array
    {
        $entries = [];

        $convo = ExhibitorConversation::where('exhibitor_id', $exhibitor->id)
            ->where('participation_id', $participation->id)
            ->first();

        if ($convo) {
            foreach ($convo->messages()->orderByDesc('id')->limit(20)->get() as $m) {
                $entries[] = [
                    'type' => 'message',
                    'side' => $m->sender_side,
                    'title' => $m->sender_side === 'attendee' ? 'Sent you a message' : 'Your team replied',
                    'detail' => mb_strimwidth((string) $m->body, 0, 160, '…'),
                    'at' => $m->created_at,
                ];
            }
        }

        $requests = ExhibitorMeetingRequest::where('exhibitor_id', $exhibitor->id)
            ->where('participation_id', $participation->id)
            ->orderByDesc('id')
            ->get();

        foreach ($requests as $r) {
            $entries[] = [
                'type' => 'meeting',
                'side' => 'attendee',
                'title' => 'Requested a meeting'.($r->status !== 'requested' ? ' · '.ucfirst($r->status) : ''),
                'detail' => $r->subject ?: ($r->agenda ? mb_strimwidth($r->agenda, 0, 160, '…') : 'No agenda given'),
                'at' => $r->created_at,
            ];
        }

        foreach ($this->boothScans($exhibitor, [$participation->id]) as $scan) {
            $entries[] = [
                'type' => 'visit',
                'side' => 'attendee',
                'title' => 'Visited your booth',
                'detail' => $scan->station_name ? 'Scanned at '.$scan->station_name : 'Badge scanned at the booth',
                'at' => $scan->scanned_at,
            ];
        }

        if ($this->hasBookmarked($participation, $exhibitor)) {
            $entries[] = [
                'type' => 'bookmark',
                'side' => 'attendee',
                'title' => 'Saved your booth',
                'detail' => 'Bookmarked you in the event app',
                'at' => null,
            ];
        }

        usort($entries, fn ($a, $b) => ($b['at']?->timestamp ?? 0) <=> ($a['at']?->timestamp ?? 0));

        return array_map(fn ($e) => [
            'type' => $e['type'],
            'side' => $e['side'],
            'title' => $e['title'],
            'detail' => $e['detail'],
            'at' => $e['at']?->toIso8601String(),
        ], $entries);
    }

    /** hot | warm | cold, from a 0–100 score. */
    public function temperature(int $score): string
    {
        return match (true) {
            $score >= self::HOT => 'hot',
            $score >= self::WARM => 'warm',
            default => 'cold',
        };
    }

    // ── Signal collection ───────────────────────────────────────────────────

    /** participation_id => ['count', 'last_at', 'preview'] for attendee-sent messages. */
    private function messageSignals(Exhibitor $exhibitor): Collection
    {
        $convos = ExhibitorConversation::where('exhibitor_id', $exhibitor->id)
            ->get(['id', 'participation_id']);

        if ($convos->isEmpty()) {
            return collect();
        }

        $messages = ExhibitorMessage::whereIn('conversation_id', $convos->pluck('id'))
            ->where('sender_side', 'attendee')
            ->orderByDesc('id')
            ->get(['conversation_id', 'body', 'created_at']);

        $byConvo = $messages->groupBy('conversation_id');

        return $convos
            ->mapWithKeys(function (ExhibitorConversation $c) use ($byConvo) {
                $group = $byConvo->get($c->id);

                if (! $group || $group->isEmpty()) {
                    return [];
                }

                return [$c->participation_id => [
                    'count' => $group->count(),
                    'last_at' => $group->first()->created_at,
                    'preview' => mb_strimwidth((string) $group->first()->body, 0, 120, '…'),
                ]];
            });
    }

    /** participation_id => ['count', 'status', 'last_at'] for meeting requests. */
    private function meetingSignals(Exhibitor $exhibitor): Collection
    {
        return ExhibitorMeetingRequest::where('exhibitor_id', $exhibitor->id)
            ->orderByDesc('id')
            ->get(['participation_id', 'status', 'subject', 'created_at'])
            ->groupBy('participation_id')
            ->map(fn (Collection $group) => [
                'count' => $group->count(),
                'status' => $group->first()->status,
                'subject' => $group->first()->subject,
                'last_at' => $group->first()->created_at,
            ]);
    }

    /** participation_id => true for attendees who saved this booth in the app. */
    private function bookmarkSignals(Exhibitor $exhibitor): Collection
    {
        return Participation::query()
            ->where('event_id', $exhibitor->event_id)
            ->whereRaw(
                "coalesce(participations.meta->'bookmarks'->'exhibitor', '[]'::jsonb) @> ?::jsonb",
                [json_encode([$exhibitor->uuid])],
            )
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => true]);
    }

    /** participation_id => ['count', 'last_at'] for badge scans at this booth. */
    private function visitSignals(Exhibitor $exhibitor): Collection
    {
        return $this->boothScans($exhibitor)
            ->groupBy('participation_id')
            ->map(fn (Collection $group) => [
                'count' => $group->count(),
                'last_at' => $group->max('scanned_at'),
            ]);
    }

    /**
     * Booth check-ins for this exhibitor. A booth is a check_in_stations row of
     * type "booth" whose meta points back at the exhibitor (see the organizer's
     * Exhibitors Scanning screen).
     *
     * @param  array<int>|null  $participationIds  narrow to specific attendees
     */
    private function boothScans(Exhibitor $exhibitor, ?array $participationIds = null): Collection
    {
        $stations = CheckInStation::where('event_id', $exhibitor->event_id)
            ->where('type', 'booth')
            ->whereRaw("meta->>'exhibitor_id' = ?", [(string) $exhibitor->id])
            ->pluck('name', 'id');

        if ($stations->isEmpty()) {
            return collect();
        }

        $query = CheckIn::where('event_id', $exhibitor->event_id)
            ->whereIn('station_id', $stations->keys())
            ->whereNotNull('participation_id')
            ->whereNotNull('scanned_at');

        if ($participationIds !== null) {
            $query->whereIn('participation_id', $participationIds);
        }

        return $query->orderByDesc('scanned_at')
            ->get(['station_id', 'participation_id', 'scanned_at'])
            ->each(fn ($scan) => $scan->station_name = $stations->get($scan->station_id));
    }

    // ── Profile fit ─────────────────────────────────────────────────────────

    /**
     * What this booth is here to sell, lower-cased: its own tags plus its
     * product names. Matched against the attendee's stated interests.
     *
     * @return array<int, string>
     */
    private function vocabulary(Exhibitor $exhibitor): array
    {
        $tags = collect($exhibitor->profile_data['tags'] ?? [])
            ->merge(ExhibitorProduct::where('exhibitor_id', $exhibitor->id)->pluck('name'));

        return $tags
            ->filter(fn ($t) => is_string($t) && trim($t) !== '')
            ->map(fn (string $t) => mb_strtolower(trim($t)))
            ->unique()
            ->take(30)
            ->values()
            ->all();
    }

    /**
     * Attendees whose interests overlap the booth's vocabulary. Bounded: a
     * profile match is the weakest signal we have, so it must not be allowed to
     * flood the list on a 10,000-badge event.
     *
     * @param  array<int, string>  $vocabulary
     * @return Collection<int, int>
     */
    private function fitCandidates(Exhibitor $exhibitor, array $vocabulary, Collection $exclude): Collection
    {
        if (! $vocabulary) {
            return collect();
        }

        // jsonb_exists_any() rather than the `?|` operator: `?` is a PDO
        // placeholder and cannot survive a prepared statement.
        $array = 'ARRAY['.implode(',', array_fill(0, count($vocabulary), '?')).']::text[]';
        $bindings = [...$vocabulary, ...$vocabulary];

        return Participation::query()
            ->where('event_id', $exhibitor->event_id)
            ->where('role', 'attendee')
            ->whereNotIn('id', $exclude->all() ?: [0])
            ->whereRaw(
                "(jsonb_exists_any(coalesce(profile_data->'interests', '[]'::jsonb), {$array})".
                " or jsonb_exists_any(coalesce(profile_data->'looking_for', '[]'::jsonb), {$array}))",
                $bindings,
            )
            ->orderByDesc('id')
            ->limit(self::FIT_ONLY_LIMIT)
            ->pluck('id');
    }

    /**
     * The booth keywords this attendee actually asked for.
     *
     * @param  array<int, string>  $vocabulary
     * @return array<int, string>
     */
    private function fitMatches(Participation $participation, array $vocabulary): array
    {
        if (! $vocabulary) {
            return [];
        }

        $profile = $participation->profile_data ?? [];
        $stated = collect([...(array) ($profile['interests'] ?? []), ...(array) ($profile['looking_for'] ?? [])])
            ->filter(fn ($v) => is_string($v))
            ->map(fn (string $v) => mb_strtolower(trim($v)));

        return $stated->intersect($vocabulary)->unique()->take(6)->values()->all();
    }

    // ── Candidate pool ──────────────────────────────────────────────────────

    /**
     * Load the candidates the booth is allowed to see: attendees of this event,
     * not blocked by the organizer, not the booth's own staff, and — unless
     * they contacted the booth first — not opted out of networking. Someone who
     * messaged you has already chosen to be reachable; someone who merely
     * matched a keyword has not.
     */
    private function reachable(Exhibitor $exhibitor, Collection $ids, Collection $inbound): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $ownContactIds = ExhibitorMember::where('exhibitor_id', $exhibitor->id)
            ->whereNotNull('contact_id')
            ->pluck('contact_id');

        return Participation::with('contact')
            ->where('event_id', $exhibitor->event_id)
            ->where('role', 'attendee')
            ->whereIn('id', $ids->all())
            ->whereNotIn('contact_id', $ownContactIds->all() ?: [0])
            ->where(fn ($q) => $q->whereNull('meta->blocked')->orWhere('meta->blocked', false))
            ->where(fn ($q) => $q
                ->whereNull('networking_opt_in')
                ->orWhere('networking_opt_in', true)
                ->orWhereIn('id', $inbound->all() ?: [0]))
            ->get();
    }

    // ── Scoring ─────────────────────────────────────────────────────────────

    /**
     * Turn one candidate's signals into a score, a temperature and the plain
     * sentences the UI shows. The reasons are not decoration: a rep will not
     * act on a number they cannot explain to themselves.
     */
    private function score(Participation $participation, array $signals): array
    {
        $score = 0;
        $reasons = [];
        $times = [];

        if ($m = $signals['meeting']) {
            $score += self::W_MEETING + ($m['count'] > 1 ? self::W_MEETING_EXTRA : 0);
            $reasons[] = [
                'key' => 'meeting',
                'text' => $m['count'] > 1
                    ? "Requested {$m['count']} meetings with your team"
                    : 'Requested a meeting with your team',
            ];
            $times[] = $m['last_at'];
        }

        if ($msg = $signals['messages']) {
            $score += min(self::W_MESSAGE_CAP, self::W_MESSAGE + (($msg['count'] - 1) * self::W_MESSAGE_EXTRA));
            $reasons[] = [
                'key' => 'message',
                'text' => $msg['count'] > 1
                    ? "Sent your booth {$msg['count']} messages"
                    : 'Messaged your booth',
            ];
            $times[] = $msg['last_at'];
        }

        if ($signals['bookmarked']) {
            $score += self::W_BOOKMARK;
            $reasons[] = ['key' => 'bookmark', 'text' => 'Saved your booth in the event app'];
        }

        if ($v = $signals['visits']) {
            $score += min(self::W_VISIT_CAP, self::W_VISIT + (($v['count'] - 1) * self::W_VISIT_EXTRA));
            $reasons[] = [
                'key' => 'visit',
                'text' => $v['count'] > 1
                    ? "Visited your booth {$v['count']} times"
                    : 'Visited your booth',
            ];
            $times[] = $v['last_at'];
        }

        if ($fit = $signals['fit']) {
            $score += min(self::W_FIT_CAP, count($fit) * self::W_FIT);
            $reasons[] = ['key' => 'fit', 'text' => 'Looking for '.implode(', ', $fit)];
        }

        $last = collect($times)->filter()->max();

        // Recency matters more than volume at a three-day event: a signal from
        // this morning is a person still in the building.
        if ($last instanceof Carbon && $last->gt(now()->subDay())) {
            $score += self::W_FRESH;
            $reasons[] = ['key' => 'fresh', 'text' => 'Active in the last 24 hours'];
        }

        $score = min(100, $score);

        return [
            'participation' => $participation,
            'score' => $score,
            'temperature' => $this->temperature($score),
            'reasons' => $reasons,
            'signals' => [
                'meetings' => $signals['meeting']['count'] ?? 0,
                'messages' => $signals['messages']['count'] ?? 0,
                'visits' => $signals['visits']['count'] ?? 0,
                'bookmarked' => (bool) $signals['bookmarked'],
                'fit' => $signals['fit'],
            ],
            'last_message' => $signals['messages']['preview'] ?? null,
            'meeting_status' => $signals['meeting']['status'] ?? null,
            'last_signal_at' => $last instanceof Carbon ? $last : null,
        ];
    }

    private function hasBookmarked(Participation $participation, Exhibitor $exhibitor): bool
    {
        return in_array($exhibitor->uuid, (array) ($participation->meta['bookmarks']['exhibitor'] ?? []), true);
    }
}
