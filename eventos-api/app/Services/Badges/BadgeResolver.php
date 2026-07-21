<?php

namespace App\Services\Badges;

use App\Models\BadgeDesign;
use App\Models\Event;
use App\Models\ExhibitorMember;
use App\Models\Participation;
use App\Support\BadgeAudience;
use Illuminate\Support\Collection;

/**
 * Which badge design gets printed for a given person.
 *
 * Resolution is a fixed ladder, most specific first — every rung is something
 * an organizer explicitly did, so a surprising badge can always be traced back
 * to one decision:
 *
 *   1. a design pinned onto the participation itself (`meta.badge_design_id`) —
 *      what the guest-badge wizard writes, and what "reprint on this template"
 *      writes for one person;
 *   2. the guest design whose sub-type matches (`meta.guest_type` = "Media" →
 *      the `guest`/Media design);
 *   3. the design for the person's audience (attendee / speaker / exhibitor …);
 *   4. the event's default design;
 *   5. nothing — the caller reports "no badge configured" rather than inventing
 *      one, because a blank badge printed onto real card stock is worse than a
 *      refusal.
 *
 * Designs are loaded once per event and filtered in memory: a print run asks
 * about hundreds of people at a time and the per-event design count is single
 * digits.
 */
class BadgeResolver
{
    /** @var array<int, Collection<int, BadgeDesign>> keyed by event id */
    private array $cache = [];

    public function forParticipation(Participation $participation): ?BadgeDesign
    {
        $designs = $this->designsFor($participation->event_id);

        // 1. Pinned to this person.
        $pinned = $participation->meta['badge_design_id'] ?? null;
        if ($pinned && $found = $designs->firstWhere('id', (int) $pinned)) {
            return $found;
        }

        $guestType = $participation->meta['guest_type'] ?? null;
        $audience = BadgeAudience::forParticipation(
            $participation,
            $this->exhibitorTypeFor($participation),
        );

        return $this->pick($designs, $audience, $guestType);
    }

    /** The design an audience would get, with no specific person in hand. */
    public function forAudience(Event $event, ?BadgeAudience $audience, ?string $guestType = null): ?BadgeDesign
    {
        return $this->pick($this->designsFor($event->id), $audience, $guestType);
    }

    /**
     * @param  Collection<int, BadgeDesign>  $designs
     */
    private function pick(Collection $designs, ?BadgeAudience $audience, ?string $guestType): ?BadgeDesign
    {
        // 2. Guest sub-type, matched case-insensitively so "media" and "Media"
        //    are the same pass.
        if ($guestType) {
            $match = $designs->first(fn (BadgeDesign $d) => $this->audienceOf($d) === BadgeAudience::Guest
                && strcasecmp((string) ($d->meta['guest_type'] ?? ''), $guestType) === 0);

            if ($match) {
                return $match;
            }
        }

        // 3. The audience's own design. For guests with an unrecognised
        //    sub-type this lands on the generic guest design, if there is one.
        if ($audience) {
            $match = $designs->first(fn (BadgeDesign $d) => $this->audienceOf($d) === $audience);

            if ($match) {
                return $match;
            }
        }

        // 4. Whatever the organizer marked as the event default. 5. Otherwise null.
        return $designs->firstWhere('is_default', true);
    }

    private function audienceOf(BadgeDesign $design): ?BadgeAudience
    {
        return BadgeAudience::tryNormalize($design->badge_for);
    }

    /**
     * `exhibitors.type` for an exhibitor team member, so their badge can say
     * Sponsor rather than Exhibitor. Null for everyone else.
     */
    private function exhibitorTypeFor(Participation $participation): ?string
    {
        if (! in_array($participation->role, ['partner_member', 'exhibitor'], true)) {
            return null;
        }

        return ExhibitorMember::where('participation_id', $participation->id)
            ->with('exhibitor:id,type')
            ->first()?->exhibitor?->type;
    }

    /** @return Collection<int, BadgeDesign> */
    private function designsFor(int $eventId): Collection
    {
        return $this->cache[$eventId] ??= BadgeDesign::where('event_id', $eventId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }
}
