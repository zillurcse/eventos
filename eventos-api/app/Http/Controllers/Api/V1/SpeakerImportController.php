<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Previous speakers" — invite someone who has spoken at one of the organizer's
 * earlier events into the event they are building now (Showcase › Speakers ›
 * PREVIOUS SPEAKERS). The exhibitor equivalent is ExhibitorImportController;
 * the shape is the same, the details are not.
 *
 * ── Identity ─────────────────────────────────────────────────────────────────
 * A speaker is a `participations` row (role=speaker) hanging off a `contacts`
 * row, and contacts are org-scoped and keyed by email. So unlike exhibitors —
 * where identity had to be reconstructed from an email string — the same person
 * across two events is literally the same contact_id. Candidates are deduped on
 * it, showing the most recent appearance.
 *
 * ── What is copied, and what is not ──────────────────────────────────────────
 * Copied: the person's profile — photo, bio, job title, company, socials, tags,
 * and whether attendees may rate them. That is the tedious part to re-key, and
 * it is true of the person regardless of which event they are at.
 *
 * Not copied, because it describes their appearance at the *old* event:
 *   - the presentation (title + deck) — they are giving a new talk, so this is
 *     opt-in rather than assumed;
 *   - `is_featured` — featuring someone on the home page is an editorial call
 *     you make per event, so an import never silently promotes 20 people;
 *   - `sort_order` — positions belong to the old line-up;
 *   - the category, unless the new event happens to have one by the same name
 *     (speaker categories live in event.meta, so last year's "Keynote" is not
 *     this year's "Keynote" — we re-map by name, exactly as the exhibitor
 *     import re-maps packages, and otherwise leave it unset).
 *
 * Sessions are not touched: last year's talks belong to last year's agenda. The
 * organizer assigns the speaker to a session in this event as usual.
 *
 * No login is created or reset. The speaker already has an account from the
 * previous event (SpeakerController::store gives every speaker one), and the
 * contact — hence that login — is reused as-is, so they can sign in to the new
 * event site the moment they are imported.
 *
 * Idempotent: someone already speaking at the target event is skipped and
 * reported rather than duplicated.
 */
class SpeakerImportController extends Controller
{
    /** Profile keys that describe the appearance, not the person. */
    private const EVENT_BOUND_KEYS = [
        'category', 'presentation_title', 'presentation_file', 'presentation_file_name',
        'is_featured', 'sort_order',
    ];

    private const PRESENTATION_KEYS = ['presentation_title', 'presentation_file', 'presentation_file_name'];

    /** Speakers from the organizer's other events, one row per person. */
    public function candidates(Request $request, string $uuid): JsonResponse
    {
        $target = Event::where('uuid', $uuid)->firstOrFail();

        $rows = Participation::with(['contact', 'event:id,uuid,name,starts_at'])
            ->speakers()
            ->where('event_id', '!=', $target->id)
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->filter(fn (Participation $p) => $p->contact !== null);

        if ($request->filled('search')) {
            $term = Str::lower($request->string('search'));
            $rows = $rows->filter(fn (Participation $p) => str_contains(
                Str::lower($p->contact->fullName().' '.$p->contact->email.' '.($p->profile_data['company'] ?? '')),
                $term,
            ));
        }

        // Newest first, so the first sighting of a contact is their latest event.
        $unique = $rows->unique('contact_id')->values();

        $taken = $this->speakerContactIdsIn($target->id);

        return response()->json([
            'data' => $unique->map(function (Participation $p) use ($taken) {
                $profile = $p->profile_data ?? [];

                return [
                    'id' => $p->uuid,
                    'name' => $p->contact->fullName(),
                    'email' => $p->contact->email,
                    'image_url' => $profile['image_url'] ?? null,
                    'designation' => $profile['designation'] ?? '',
                    'company' => $profile['company'] ?? '',
                    'category' => $profile['category'] ?? '',
                    'presentation_title' => $profile['presentation_title'] ?? '',
                    'event' => [
                        'id' => $p->event?->uuid,
                        'name' => $p->event?->name,
                        'starts_at' => $p->event?->starts_at?->toIso8601String(),
                    ],
                    'already_added' => $taken->contains($p->contact_id),
                ];
            }),
            'meta' => ['total' => $unique->count()],
        ]);
    }

    /** Bring the chosen speakers into this event. */
    public function store(Request $request, string $uuid): JsonResponse
    {
        $target = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'speakers' => ['required', 'array', 'min:1', 'max:100'],
            'speakers.*' => ['required', 'string'],
            'include' => ['nullable', 'array'],
            'include.presentation' => ['nullable', 'boolean'],
        ]);

        $withPresentation = (bool) ($data['include']['presentation'] ?? false);

        // RLS keeps this inside the organization; excluding the target event means
        // a uuid from this event (or another tenant) simply resolves to nothing.
        $sources = Participation::with('contact')
            ->speakers()
            ->whereIn('uuid', $data['speakers'])
            ->where('event_id', '!=', $target->id)
            ->get();

        $taken = $this->speakerContactIdsIn($target->id);
        $categories = $this->categoryNames($target);

        $imported = [];
        $skipped = [];

        DB::transaction(function () use ($sources, $target, $withPresentation, $categories, &$taken, &$imported, &$skipped) {
            foreach ($sources as $source) {
                if ($taken->contains($source->contact_id)) {
                    $skipped[] = [
                        'name' => $source->contact?->fullName() ?: 'Speaker',
                        'reason' => 'Already speaking at this event',
                    ];

                    continue;
                }

                $imported[] = $this->copy($source, $target, $withPresentation, $categories);

                // The same person could be picked from two different past events
                // in one batch — the first copy claims them.
                $taken->push($source->contact_id);
            }
        });

        return response()->json([
            'data' => collect($imported)->map(fn (Participation $p) => [
                'id' => $p->uuid,
                'name' => $p->contact?->fullName(),
            ]),
            'meta' => [
                'imported' => count($imported),
                'skipped' => $skipped,
            ],
        ], 201);
    }

    /** Re-seat one speaker at the target event. */
    private function copy(Participation $source, Event $target, bool $withPresentation, Collection $categories): Participation
    {
        $profile = collect($source->profile_data ?? [])
            ->except(self::EVENT_BOUND_KEYS);

        if ($withPresentation) {
            $profile = $profile->merge(
                collect($source->profile_data ?? [])->only(self::PRESENTATION_KEYS)
            );
        }

        // Last year's "Keynote" is a different row from this year's; keep them in
        // the category only when this event actually has one by that name.
        $category = $source->profile_data['category'] ?? '';
        if ($category !== '' && $categories->contains(fn ($name) => Str::lower($name) === Str::lower($category))) {
            $profile = $profile->put('category', $category);
        }

        $participation = Participation::create([
            'event_id' => $target->id,
            'contact_id' => $source->contact_id, // same person, same login
            'role' => 'speaker',
            'status' => 'confirmed',
        ]);

        $participation->update(['profile_data' => $profile->all()]);

        return $participation->load('contact');
    }

    /** Who already speaks at this event — what makes the import idempotent. */
    private function speakerContactIdsIn(int $eventId): Collection
    {
        return Participation::where('event_id', $eventId)
            ->speakers()
            ->pluck('contact_id');
    }

    /** The target event's speaker categories, by name (event.meta). */
    private function categoryNames(Event $event): Collection
    {
        return collect($event->meta['speaker_categories'] ?? [])->pluck('name');
    }
}
