<?php

namespace App\Support;

use App\Models\Participation;

/**
 * Who a badge design is printed for.
 *
 * This is deliberately NOT the same vocabulary as `participations.role`. A role
 * says what someone may do in the event (attendee|speaker|partner_member|staff);
 * an audience says which piece of card gets printed for them. They mostly line
 * up, but not always:
 *
 *   - `partner_member` (an exhibitor's team) splits into `exhibitor` and
 *     `sponsor` depending on how the organizer classified the company;
 *   - `guest` has no role at all — it is a badge issued to press / VVIP / a
 *     partner who was never registered, and it carries a free-text sub-type
 *     (Media, VVIP, …) so one event can have several guest designs.
 *
 * Keeping the two vocabularies apart means adding a badge audience never
 * requires touching the permission model.
 */
enum BadgeAudience: string
{
    case Attendee = 'attendee';
    case Speaker = 'speaker';
    case Exhibitor = 'exhibitor';
    case Sponsor = 'sponsor';
    case Staff = 'staff';
    case Organizer = 'organizer';
    case Guest = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::Attendee => 'Attendee',
            self::Speaker => 'Speaker',
            self::Exhibitor => 'Exhibitor',
            self::Sponsor => 'Sponsor',
            self::Staff => 'Staff',
            self::Organizer => 'Organizer',
            self::Guest => 'Guest',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /**
     * Accepts anything that was ever stored in `badge_designs.badge_for` —
     * including the pre-enum title-case values ("Attendee", "VIP", "Press") the
     * first version of the templates page wrote. Unknown labels are treated as
     * guest sub-types rather than discarded, because that is what "VIP" and
     * "Press" always meant.
     */
    public static function tryNormalize(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::tryFrom(strtolower(trim($value)));
    }

    /**
     * The audience a participation should be badged as, before any per-person
     * override. `guest` wins over the role because a press pass issued to
     * someone who also registered as an attendee is still a press pass.
     *
     * `$exhibitorType` is the `exhibitors.type` of the company a
     * `partner_member` belongs to ('exhibitor' | 'sponsor'); BadgeResolver looks
     * it up, so this stays a pure function of values already in hand.
     */
    public static function forParticipation(Participation $participation, ?string $exhibitorType = null): self
    {
        if (($participation->meta['guest_type'] ?? null) !== null) {
            return self::Guest;
        }

        return match ($participation->role) {
            'speaker' => self::Speaker,
            'staff' => self::Staff,
            'guest' => self::Guest,
            // `partner_member` is the documented role for an exhibitor's team;
            // `exhibitor` also occurs in the data (older create sites wrote it).
            // Both are badged the same way.
            'partner_member', 'exhibitor' => $exhibitorType === 'sponsor' ? self::Sponsor : self::Exhibitor,
            default => self::Attendee,
        };
    }
}
