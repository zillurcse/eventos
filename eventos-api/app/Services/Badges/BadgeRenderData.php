<?php

namespace App\Services\Badges;

use App\Models\Event;
use App\Models\Participation;
use App\Support\BadgeAudience;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a participation into the flat token map a saved badge design is merged
 * against at render time.
 *
 * ── Why a flat map of strings ─────────────────────────────────────────────────
 * The canvas editor stores every element as a box carrying a `key` (see
 * useCanvasStore's drop handler, which copies `key` off the element-library
 * item). So "dynamic data" is nothing more than: for each box, if the map has
 * `box.key`, draw that instead of the authored placeholder text. Keeping the
 * map flat and stringly-typed means the same payload drives the admin preview,
 * the attendee's My Badges page and the print job, with no renderer knowing
 * anything about Participation.
 *
 * The key vocabulary is the contract between this class and
 * BadgeDesignController::elementLibrary — a key offered in the sidebar that is
 * never produced here renders as its placeholder forever, which is why both
 * lists live one method apart from each other.
 */
class BadgeRenderData
{
    /** The tokens every design may reference. Order is the sidebar's order. */
    public const KEYS = [
        'full_name', 'first_name', 'last_name', 'designation', 'company',
        'country', 'email', 'phone', 'role_label', 'guest_type',
        'event_name', 'event_logo', 'event_dates', 'event_venue', 'event_city',
        'avatar', 'qrcode',
    ];

    /**
     * @return array<string, string> token => value, empty strings for anything
     *                               this person simply does not have
     */
    public function for(Participation $participation): array
    {
        $contact = $participation->contact;
        $profile = $participation->profile_data ?? [];
        $meta = $participation->meta ?? [];
        $event = $participation->event;

        $guestType = $meta['guest_type'] ?? null;

        return [
            'full_name' => $contact?->fullName() ?: ($profile['name'] ?? ''),
            'first_name' => $contact?->first_name ?? '',
            'last_name' => $contact?->last_name ?? '',
            'designation' => $profile['designation'] ?? $profile['job_title'] ?? '',
            'company' => $profile['company'] ?? '',
            'country' => $profile['country'] ?? '',
            // Guests imported without an address carry an unroutable
            // placeholder (see GuestBadgeController) — never print that.
            'email' => ($contact?->meta['email_placeholder'] ?? false) ? '' : ($contact?->email ?? ''),
            'phone' => $profile['phone'] ?? $contact?->phone ?? '',
            // What the badge calls this person. A guest badge shows its
            // sub-type ("Media"), which is the whole point of guest badges.
            'role_label' => $guestType ?: BadgeAudience::forParticipation($participation)->label(),
            'guest_type' => $guestType ?: '',
            'event_name' => $event?->name ?? '',
            'event_logo' => $event ? $this->eventLogo($event) : '',
            'event_dates' => $event ? $this->eventDates($event) : '',
            'event_venue' => $event?->primaryVenue?->name ?? '',
            'event_city' => $event?->primaryVenue?->city ?? '',
            // Same ladder the delegate directory reads (DelegateController), so
            // the face on someone's badge is the face everyone else sees next
            // to their name: meta wins (imports and admin edits write there),
            // then the profile form's own field, then the legacy key.
            'avatar' => $meta['avatar_url'] ?? $profile['avatar_url'] ?? $profile['image_url'] ?? '',
            // Scanned at the gates. `participations.uuid` is what
            // CheckInController already looks up, so a printed badge and the
            // attendee's on-screen badge resolve to the same person.
            'qrcode' => $participation->uuid,
        ];
    }

    /**
     * Placeholder values for previewing a design that has no real person behind
     * it yet (the templates list, the wizard's design step).
     *
     * @return array<string, string>
     */
    public function sample(Event $event, ?BadgeAudience $audience = null, ?string $guestType = null): array
    {
        $label = $guestType ?: ($audience?->label() ?? 'Attendee');

        return [
            'full_name' => 'Ananya Sharma',
            'first_name' => 'Ananya',
            'last_name' => 'Sharma',
            'designation' => 'VP Engineering',
            'company' => 'Google',
            'country' => 'India',
            'email' => 'ananya@example.com',
            'phone' => '+91 98765 43210',
            'role_label' => $label,
            'guest_type' => $guestType ?: '',
            'event_name' => $event->name,
            'event_logo' => $this->eventLogo($event),
            'event_dates' => $this->eventDates($event),
            'event_venue' => $event->primaryVenue?->name ?: 'Hall 4, Expo Centre',
            'event_city' => $event->primaryVenue?->city ?: 'Dhaka',
            'avatar' => '',
            // A real-looking but meaningless uuid: the preview QR must scan to
            // nothing rather than to somebody else's participation.
            'qrcode' => '00000000-0000-0000-0000-000000000000',
        ];
    }

    /**
     * The run of the event as one printable line, in the event's own timezone —
     * "12 – 14 Mar 2026", collapsed to "14 Mar 2026" for a single day and to
     * "28 Feb – 3 Mar 2026" when it straddles a month. A badge has room for one
     * short line, not a formatted range per locale.
     */
    private function eventDates(Event $event): string
    {
        $tz = $event->resolvedTimezone();
        $start = $event->starts_at?->setTimezone($tz);
        $end = $event->ends_at?->setTimezone($tz);

        if (! $start) {
            return '';
        }

        if (! $end || $start->isSameDay($end)) {
            return $start->format('j M Y');
        }

        // Drop the repeated month/year from the first date where they match, so
        // the common case reads "12 – 14 Mar 2026" rather than twice as long.
        $from = $start->isSameMonth($end) && $start->isSameYear($end)
            ? $start->format('j')
            : ($start->isSameYear($end) ? $start->format('j M') : $start->format('j M Y'));

        return "{$from} – {$end->format('j M Y')}";
    }

    /** Branding logo from event settings, absolute so any app can render it. */
    private function eventLogo(Event $event): string
    {
        $branding = $event->settings?->branding ?? [];

        if (! empty($branding['logo_url'])) {
            return $branding['logo_url'];
        }

        $cover = $event->coverFile ?? null;

        return $cover ? Storage::disk($cover->disk)->url($cover->path) : '';
    }
}
