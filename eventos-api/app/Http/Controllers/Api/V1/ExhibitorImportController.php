<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExhibitorResource;
use App\Models\Event;
use App\Models\Exhibitor;
use App\Models\ExhibitorMember;
use App\Models\ExhibitorPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Previous exhibitors" — carry an exhibitor the organizer has run before into
 * the event they are building now (Showcase › Exhibitors › PREVIOUS EXHIBITORS).
 *
 * Exhibitors are event-scoped rows: the same company appearing at three of an
 * organizer's events is three `exhibitors` rows. Re-typing that company's brand,
 * team and catalogue every year is the busywork this removes.
 *
 * ── What "the same exhibitor" means ──────────────────────────────────────────
 * There is no company entity to key on, so identity here is the admin email
 * (case-insensitive), falling back to the name when an exhibitor has no email.
 * The candidate list is deduplicated on that identity and shows the most recent
 * appearance, so a company that exhibited three years running is offered once,
 * with last year's data.
 *
 * ── What is copied, and what is deliberately not ─────────────────────────────
 * Copied: who they are (name, type, email, logo, website, description) and the
 * profile the organizer curated (about, address, socials, CTAs, spotlight, tags,
 * flags, entitlements). Optionally their team, products, documents and projects.
 *
 * Not copied, because it belongs to the *old* event and would be a lie in the
 * new one: the package (exhibitor_packages are per-event — we re-map it by name
 * when the new event has a package of the same name, otherwise leave it unset
 * for the organizer to assign), the stall number, the filter selections (the new
 * event's filters are different rows), the booth placements, and the member's
 * participation link. Status starts `active`, and the slug is minted fresh so it
 * stays unique within the new event.
 *
 * No invite emails go out on import: the team members being copied already have
 * logins from the previous event, and mailing a fresh access code to a hundred
 * people because an organizer clicked Import would be a spam incident. The
 * organizer can still use ACTIONS › Reset Password per exhibitor.
 *
 * The import is idempotent: an exhibitor already present in the target event
 * (same identity) is skipped and reported, so clicking Import twice, or picking
 * someone already added by hand, cannot create a duplicate.
 */
class ExhibitorImportController extends Controller
{
    /** Profile fields that describe the exhibitor, not their slot in one event. */
    private const EVENT_BOUND_PROFILE_KEYS = ['stall_no', 'filter_id', 'filter_selections'];

    /**
     * Exhibitors from the organizer's *other* events, one row per company,
     * flagged with whether they are already in the event being built.
     */
    public function candidates(Request $request): JsonResponse
    {
        $target = $this->targetEvent($request);

        $rows = Exhibitor::with(['event:id,uuid,name,starts_at', 'package:id,name'])
            ->withCount(['members', 'products', 'documents', 'projects'])
            ->where('event_id', '!=', $target->id)
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.Str::lower($request->string('search')).'%';
                $q->where(fn ($w) => $w->whereRaw('lower(name) like ?', [$term])
                    ->orWhereRaw('lower(email) like ?', [$term]));
            })
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        // Most recent appearance wins: the rows arrive newest-first, so the first
        // sighting of an identity is the one we keep.
        $unique = $rows->unique(fn (Exhibitor $e) => $this->identity($e))->values();

        $taken = $this->identitiesIn($target->id);

        return response()->json([
            'data' => $unique->map(fn (Exhibitor $e) => [
                'id' => $e->uuid,
                'name' => $e->name,
                'email' => $e->email,
                'type' => $e->type,
                'logo_url' => (new ExhibitorResource($e))->toArray($request)['logo_url'],
                'package_name' => $e->package?->name,
                'event' => [
                    'id' => $e->event?->uuid,
                    'name' => $e->event?->name,
                    'starts_at' => $e->event?->starts_at?->toIso8601String(),
                ],
                'counts' => [
                    'members' => (int) $e->members_count,
                    'products' => (int) $e->products_count,
                    'documents' => (int) $e->documents_count,
                    'projects' => (int) $e->projects_count,
                ],
                // Already in this event → offered, but not selectable.
                'already_added' => $taken->contains($this->identity($e)),
            ]),
            'meta' => ['total' => $unique->count()],
        ]);
    }

    /**
     * Copy the chosen exhibitors into the target event.
     *
     * One transaction for the whole batch: a half-imported exhibitor (brand but
     * no team) is worse than none, and the organizer would have to hunt for what
     * landed.
     */
    public function store(Request $request): JsonResponse
    {
        $target = $this->targetEvent($request);

        $data = $request->validate([
            'exhibitors' => ['required', 'array', 'min:1', 'max:100'],
            'exhibitors.*' => ['required', 'string'],
            'include' => ['nullable', 'array'],
            'include.members' => ['nullable', 'boolean'],
            'include.products' => ['nullable', 'boolean'],
            'include.documents' => ['nullable', 'boolean'],
            'include.projects' => ['nullable', 'boolean'],
        ]);

        $include = $data['include'] ?? [];

        // Scoped to the org by RLS, and explicitly not from the target event —
        // a uuid from another tenant, or from this same event, resolves to nothing.
        $sources = Exhibitor::with(['members', 'products', 'documents', 'projects'])
            ->whereIn('uuid', $data['exhibitors'])
            ->where('event_id', '!=', $target->id)
            ->get();

        $taken = $this->identitiesIn($target->id);
        $packages = ExhibitorPackage::where('event_id', $target->id)->pluck('id', 'name');

        $imported = [];
        $skipped = [];

        DB::transaction(function () use ($sources, $target, $include, $packages, &$taken, &$imported, &$skipped, $request) {
            foreach ($sources as $source) {
                $identity = $this->identity($source);

                if ($taken->contains($identity)) {
                    $skipped[] = ['name' => $source->name, 'reason' => 'Already in this event'];

                    continue;
                }

                $imported[] = $this->copy($source, $target, $include, $packages, $request);

                // Guard against the same company appearing twice in one batch
                // (two events, same email) — the first copy claims the identity.
                $taken->push($identity);
            }
        });

        return response()->json([
            'data' => ExhibitorResource::collection(collect($imported)),
            'meta' => [
                'imported' => count($imported),
                'skipped' => $skipped,
            ],
        ], 201);
    }

    /** Clone one exhibitor into the target event. */
    private function copy(Exhibitor $source, Event $target, array $include, Collection $packages, Request $request): Exhibitor
    {
        $profile = collect($source->profile_data ?? [])
            ->except(self::EVENT_BOUND_PROFILE_KEYS)
            ->all();

        $exhibitor = new Exhibitor([
            'event_id' => $target->id,
            'type' => $source->type,
            'name' => $source->name,
            'email' => $source->email,
            'slug' => $this->uniqueSlug($source->name, $target->id),
            'description' => $source->description,
            'website' => $source->website,
            'logo_file_id' => $source->logo_file_id, // files are org-scoped: safe to share
            'tier_rank' => $source->tier_rank,
            // Same package name in the new event → keep them on it; otherwise the
            // organizer assigns one. Never point at the old event's package row.
            'package_id' => $packages[$source->package?->name ?? ''] ?? null,
            'profile_data' => $profile,
        ]);
        // status (governance) + created_by (attribution) are not $fillable.
        $exhibitor->forceFill(['status' => 'active', 'created_by' => $request->user()?->id])->save();

        if ($include['members'] ?? false) {
            $this->copyMembers($source, $exhibitor);
        }

        foreach (['products', 'documents', 'projects'] as $relation) {
            if ($include[$relation] ?? false) {
                foreach ($source->{$relation} as $row) {
                    $copy = $row->replicate(['exhibitor_id']);
                    $copy->exhibitor_id = $exhibitor->id;
                    $copy->save();
                }
            }
        }

        return $exhibitor->fresh(['package', 'members.contact', 'products', 'documents', 'projects'])
            ->loadCount('members');
    }

    /**
     * Carry the team over. Contacts are org-scoped, so the same contact row (and
     * the login already hanging off it) is reused — the member simply gains
     * access to the new event's booth. participation_id is dropped: it points at
     * a participation in the *old* event.
     */
    private function copyMembers(Exhibitor $source, Exhibitor $exhibitor): void
    {
        foreach ($source->members as $member) {
            $copy = new ExhibitorMember([
                'exhibitor_id' => $exhibitor->id,
                'contact_id' => $member->contact_id,
                'is_lead_capturer' => $member->is_lead_capturer,
            ]);
            // role + permissions are privileged (not $fillable).
            $copy->forceFill([
                'role' => $member->role,
                'permissions' => $member->permissions,
            ])->save();
        }

        if ($source->admin_contact_id) {
            $exhibitor->update(['admin_contact_id' => $source->admin_contact_id]);
        }
    }

    /** The event being built. */
    private function targetEvent(Request $request): Event
    {
        $request->validate(['event' => ['required', 'string']]);

        return Event::where('uuid', $request->string('event'))->firstOrFail();
    }

    /**
     * How we tell "the same exhibitor" apart across events: the admin email,
     * case-insensitive, or the name when there is no email.
     */
    private function identity(Exhibitor $e): string
    {
        return $e->email
            ? 'e:'.Str::lower(trim($e->email))
            : 'n:'.Str::lower(trim($e->name));
    }

    /** Identities already present in an event — what makes the import idempotent. */
    private function identitiesIn(int $eventId): Collection
    {
        return Exhibitor::where('event_id', $eventId)
            ->get(['name', 'email'])
            ->map(fn (Exhibitor $e) => $this->identity($e));
    }

    private function uniqueSlug(string $name, int $eventId): string
    {
        $base = Str::slug($name) ?: 'exhibitor';
        $slug = $base;
        $i = 1;
        while (Exhibitor::where('event_id', $eventId)->where('slug', $slug)->withTrashed()->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
