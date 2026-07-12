<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Participation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

/**
 * Attendee-facing delegate directory ("Delegates" tab). Acts as the resolved
 * participation (ResolveParticipant middleware). Lists the event's fellow
 * attendees for networking — excludes the viewer, blocked people, and anyone
 * who opted out of networking. A connection request is a separate call
 * (ConnectionController@store).
 *
 * Search + sort + pagination are server-side (the directory must scale to
 * very large events, so the client never receives the full list), and each
 * page is annotated with live online presence (see PresenceController).
 */
class DelegateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $me = $request->attributes->get('participation_id');

        $params = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'in:az,za'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            // Targeted lookup (comma-separated participation uuids) — used by
            // the bookmarks panel to resolve saved people without paging.
            'ids' => ['nullable', 'string', 'max:8000'],
        ]);

        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['per_page'] ?? 60);

        $query = $this->directoryQuery($eventId, $me);

        if (! empty($params['ids'])) {
            $ids = array_slice(array_filter(array_map('trim', explode(',', $params['ids']))), 0, 200);
            $query->whereIn('participations.uuid', $ids);
        }

        if ($term = trim($params['q'] ?? '')) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($w) => $w
                ->whereRaw("coalesce(contacts.first_name,'')||' '||coalesce(contacts.last_name,'') ilike ?", [$like])
                ->orWhereRaw("coalesce(contacts.company,'') ilike ?", [$like])
                ->orWhereRaw("coalesce(contacts.job_title,'') ilike ?", [$like]));
        }

        $dir = ($params['sort'] ?? 'az') === 'za' ? 'desc' : 'asc';
        $query->orderByRaw("lower(coalesce(contacts.first_name,'')||' '||coalesce(contacts.last_name,'')) {$dir}")
            ->orderBy('participations.id'); // stable tiebreak across pages

        // Fetch one extra row to derive has_more without a COUNT(*) sweep.
        $rows = $query->offset(($page - 1) * $perPage)->limit($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        $rows = $rows->take($perPage);

        $online = $this->onlineMap($eventId, $rows->pluck('id'));

        return response()->json([
            'data' => $rows
                ->map(fn (Participation $p) => $this->format($p, $online[$p->id] ?? false))
                ->values(),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'has_more' => $hasMore],
        ]);
    }

    /**
     * The people this viewer is allowed to see in the directory: fellow
     * attendees of the event, minus themselves, anyone the organizer blocked,
     * and anyone who opted out of networking.
     */
    private function directoryQuery(int|string $eventId, int|string $me): Builder
    {
        return Participation::query()
            ->with('contact')
            ->select('participations.*')
            ->join('contacts', 'contacts.id', '=', 'participations.contact_id')
            ->where('participations.event_id', $eventId)
            ->where('participations.role', 'attendee')
            ->where('participations.id', '!=', $me)
            // Not blocked by the organizer.
            ->where(fn ($q) => $q->whereNull('participations.meta->blocked')->orWhere('participations.meta->blocked', false))
            // Discoverable: opted in, or hasn't made a choice (default visible).
            ->where(fn ($q) => $q->whereNull('participations.networking_opt_in')->orWhere('participations.networking_opt_in', true));
    }

    /**
     * "People like you" — the delegates whose designation or company matches the
     * viewer's own, best match first. Shown as a strip above the directory.
     * GET /events/{event}/delegates/similar
     *
     * Ranked: same job title AND company (3) > same job title (2) > same
     * company (1). A viewer with neither field filled in gets an empty list —
     * there is nothing to be similar to.
     */
    public function similar(Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $me = $request->attributes->get('participation_id');

        $limit = (int) ($request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:24'],
        ])['limit'] ?? 12);

        $mine = Participation::with('contact')->find($me);
        $profile = $mine?->profile_data ?? [];

        $jobTitle = trim((string) ($mine?->contact?->job_title ?? $profile['designation'] ?? ''));
        $company = trim((string) ($mine?->contact?->company ?? $profile['company'] ?? ''));

        if ($jobTitle === '' && $company === '') {
            return response()->json(['data' => [], 'meta' => ['job_title' => '', 'company' => '']]);
        }

        // Match on the same values the directory *displays*: the contact record
        // first, falling back to the participation's profile_data (that's where
        // a self-registered attendee's details land).
        $theirTitle = "lower(trim(coalesce(nullif(contacts.job_title,''), participations.profile_data->>'designation', '')))";
        $theirCompany = "lower(trim(coalesce(nullif(contacts.company,''), participations.profile_data->>'company', '')))";

        // Score in SQL so the ranking survives the LIMIT (we must not rank a
        // page we already truncated).
        $score = "(case when {$theirTitle} = ? and ? <> '' then 2 else 0 end)"
            ." + (case when {$theirCompany} = ? and ? <> '' then 1 else 0 end)";

        $needles = [mb_strtolower($jobTitle), $jobTitle, mb_strtolower($company), $company];

        $rows = $this->directoryQuery($eventId, $me)
            ->selectRaw("({$score}) as match_score", $needles)
            ->whereRaw("({$score}) > 0", $needles)
            ->orderByDesc('match_score')
            ->orderByRaw("lower(coalesce(contacts.first_name,'')||' '||coalesce(contacts.last_name,'')) asc")
            ->limit($limit)
            ->get();

        $online = $this->onlineMap($eventId, $rows->pluck('id'));

        return response()->json([
            'data' => $rows->map(fn (Participation $p) => array_merge(
                $this->format($p, $online[$p->id] ?? false),
                ['match' => $this->matchLabel((int) $p->match_score, $jobTitle, $company)],
            ))->values(),
            'meta' => ['job_title' => $jobTitle, 'company' => $company],
        ]);
    }

    /** Why this person surfaced — shown under the name in the strip. */
    private function matchLabel(int $score, string $jobTitle, string $company): string
    {
        return match ($score) {
            3 => $jobTitle.' at '.$company,
            2 => $jobTitle,
            default => $company,
        };
    }

    /** participation id => currently online, in one MGET for the page. */
    private function onlineMap(int|string $eventId, Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        try {
            $values = Redis::mget(
                $ids->map(fn ($id) => PresenceController::key($eventId, $id))->all(),
            );
        } catch (\Throwable) {
            return []; // presence is best-effort — directory still works
        }

        return $ids->values()
            ->mapWithKeys(fn ($id, $i) => [$id => (bool) ($values[$i] ?? false)])
            ->all();
    }

    private function format(Participation $p, bool $online): array
    {
        $c = $p->contact;
        $meta = $p->meta ?? [];
        $profile = $p->profile_data ?? [];

        return [
            'id' => $p->uuid,
            'name' => $c ? (trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: null) : null,
            'company' => $c?->company ?? ($profile['company'] ?? ''),
            'job_title' => $c?->job_title ?? ($profile['designation'] ?? ''),
            'avatar_url' => $meta['avatar_url'] ?? ($profile['avatar_url'] ?? ($profile['image_url'] ?? null)),
            'online' => $online,
        ];
    }
}
