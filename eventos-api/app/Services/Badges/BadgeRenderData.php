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
        'event_name', 'event_logo', 'avatar', 'qrcode',
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
            'avatar' => $profile['image_url'] ?? $profile['avatar_url'] ?? '',
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
            'avatar' => '',
            // A real-looking but meaningless uuid: the preview QR must scan to
            // nothing rather than to somebody else's participation.
            'qrcode' => '00000000-0000-0000-0000-000000000000',
        ];
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
