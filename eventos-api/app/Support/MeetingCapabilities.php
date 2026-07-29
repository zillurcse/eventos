<?php

namespace App\Support;

use App\Models\EventSetting;
use App\Models\Exhibitor;
use App\Models\ExhibitorMeetingRequest;
use App\Models\Meeting;
use App\Models\Participation;

/**
 * Communication → Meetings (event_settings.meeting).
 *
 * Permission matrix (who may request a meeting with whom), per-role caps on
 * outgoing requests and confirmed meetings, and slot duration for open-all grids.
 * Missing restriction config means unrestricted — same as an organizer who has
 * never opened the screen.
 */
class MeetingCapabilities
{
    public const ROLES = ['attendee', 'speaker', 'exhibitor', 'sponsor'];

    public static function config(?EventSetting $setting): array
    {
        return is_array($setting?->meeting) ? $setting->meeting : [];
    }

    public static function configForEvent(int $eventId): array
    {
        $setting = EventSetting::where('event_id', $eventId)->first();

        return self::config($setting);
    }

    /**
     * Navigation & Menu › Web App Tabs — is the Meetings tab switched on?
     * Empty config defaults to enabled (organizer never opened that screen).
     */
    public static function meetingsTabEnabled(?EventSetting $setting): bool
    {
        $items = $setting->navigation['web_app_tabs']['items'] ?? null;
        if (! is_array($items) || $items === []) {
            return true;
        }

        foreach ($items as $item) {
            if (! is_array($item) || empty($item['key'])) {
                continue;
            }
            $key = strtolower((string) $item['key']);
            if (in_array($key, ['meetings', 'meeting'], true)) {
                return ($item['enabled'] ?? false) === true;
            }
        }

        return false;
    }

    public static function abortUnlessMeetingsEnabled(
        int $eventId,
        string $message = 'Meetings are not enabled for this event.',
    ): void {
        $setting = EventSetting::where('event_id', $eventId)->first();
        abort_unless(self::meetingsTabEnabled($setting), 403, $message);
    }

    /** Whether `$fromRole` may send a meeting request to `$toRole`. */
    public static function allows(array $meeting, string $fromRole, string $toRole): bool
    {
        $matrix = $meeting['permissions'] ?? null;
        if (! is_array($matrix)) {
            return true;
        }

        $row = $matrix[$fromRole] ?? null;
        if (! is_array($row)) {
            return true;
        }

        return (bool) ($row[$toRole] ?? true);
    }

    public static function allowsFor(Participation $from, Participation $to, array $meeting): bool
    {
        return self::allows(
            $meeting,
            CommunicationCapabilities::roleFor($from),
            CommunicationCapabilities::roleFor($to),
        );
    }

    /** Matrix role for a booth company (exhibitor vs sponsor). */
    public static function roleForExhibitor(Exhibitor $exhibitor): string
    {
        return $exhibitor->type === 'sponsor' ? 'sponsor' : 'exhibitor';
    }

    /** Whether `$from` may request a booth meeting with this exhibitor company. */
    public static function allowsExhibitor(Participation $from, Exhibitor $exhibitor, array $meeting): bool
    {
        return self::allows(
            $meeting,
            CommunicationCapabilities::roleFor($from),
            self::roleForExhibitor($exhibitor),
        );
    }

    public static function abortUnlessAllowsExhibitor(
        Participation $from,
        Exhibitor $exhibitor,
        array $meeting,
        string $message = 'The organizer has not enabled meetings with this role.',
    ): void {
        abort_unless(self::allowsExhibitor($from, $exhibitor, $meeting), 403, $message);
    }

    /** Roles the given participant may request a meeting with. */
    public static function allowedTargetRoles(array $meeting, string $myRole): array
    {
        if (! in_array($myRole, self::ROLES, true)) {
            $myRole = 'attendee';
        }

        $matrix = $meeting['permissions'] ?? null;
        if (! is_array($matrix)) {
            return self::ROLES;
        }

        $row = $matrix[$myRole] ?? null;
        if (! is_array($row)) {
            return self::ROLES;
        }

        return array_values(array_filter(
            self::ROLES,
            fn (string $role) => (bool) ($row[$role] ?? true),
        ));
    }

    /**
     * Per-role caps from Communication → Meetings → Meeting Restriction.
     * When the organizer never configured restrictions, caps are unlimited.
     *
     * @return array{requests: int, confirmed: int}
     */
    public static function limitsFor(array $meeting, string $role): array
    {
        $restrictions = $meeting['restrictions'] ?? null;
        if (! is_array($restrictions)) {
            return ['requests' => PHP_INT_MAX, 'confirmed' => PHP_INT_MAX];
        }

        $row = is_array($restrictions[$role] ?? null) ? $restrictions[$role] : [];

        return [
            'requests' => max(0, (int) ($row['requests'] ?? 10)),
            'confirmed' => max(0, (int) ($row['confirmed'] ?? 10)),
        ];
    }

    public static function slotDurationMinutes(array $meeting): int
    {
        $d = (int) ($meeting['slot_duration'] ?? 30);

        return in_array($d, [10, 15, 30], true) ? $d : 30;
    }

    /** Communication → Meetings › Intelligent Meeting toggle. */
    public static function isIntelligent(array $meeting): bool
    {
        return (bool) ($meeting['intelligent'] ?? false);
    }

    /** Organizer-defined meeting places, e.g. ["Hall 4"]. */
    public static function locations(array $meeting): array
    {
        $locations = is_array($meeting['locations'] ?? null) ? $meeting['locations'] : [];

        return collect($locations)
            ->filter(fn ($l) => is_string($l) && trim($l) !== '')
            ->map(fn (string $l) => trim($l))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Outgoing meeting requests (delegate + booth) still active — requested or confirmed.
     */
    public static function outgoingRequestCount(int $eventId, int $participationId): int
    {
        $delegate = Meeting::where('event_id', $eventId)
            ->where('organizer_participation_id', $participationId)
            ->whereIn('status', ['requested', 'confirmed'])
            ->count();

        $booth = ExhibitorMeetingRequest::where('event_id', $eventId)
            ->where('participation_id', $participationId)
            ->whereIn('status', ['requested', 'confirmed'])
            ->count();

        return $delegate + $booth;
    }

    /** Confirmed meetings this participant is part of (delegate + booth). */
    public static function confirmedCount(int $eventId, int $participationId): int
    {
        $delegate = Meeting::where('event_id', $eventId)
            ->where('status', 'confirmed')
            ->where(fn ($q) => $q
                ->where('organizer_participation_id', $participationId)
                ->orWhereHas('participants', fn ($p) => $p->where('participations.id', $participationId)))
            ->count();

        $booth = ExhibitorMeetingRequest::where('event_id', $eventId)
            ->where('participation_id', $participationId)
            ->where('status', 'confirmed')
            ->count();

        return $delegate + $booth;
    }

    public static function canSendRequest(int $eventId, Participation $participation, array $meeting): bool
    {
        $limits = self::limitsFor($meeting, CommunicationCapabilities::roleFor($participation));

        return self::outgoingRequestCount($eventId, $participation->id) < $limits['requests'];
    }

    public static function canConfirm(int $eventId, Participation $participation, array $meeting): bool
    {
        $limits = self::limitsFor($meeting, CommunicationCapabilities::roleFor($participation));

        return self::confirmedCount($eventId, $participation->id) < $limits['confirmed'];
    }

    public static function abortUnlessCanSendRequest(
        int $eventId,
        Participation $participation,
        array $meeting,
        string $message = 'You have reached the maximum number of meeting requests.',
    ): void {
        abort_unless(self::canSendRequest($eventId, $participation, $meeting), 422, $message);
    }

    public static function abortUnlessCanConfirm(
        int $eventId,
        Participation $participation,
        array $meeting,
        string $message = 'You have reached the maximum number of confirmed meetings.',
    ): void {
        abort_unless(self::canConfirm($eventId, $participation, $meeting), 422, $message);
    }

    /**
     * Participant-facing payload for the Meetings tab and Connect modal.
     *
     * @return array{
     *   enabled: bool,
     *   role: string,
     *   allowed_roles: list<string>,
     *   restrictions: array{requests: int, confirmed: int, requests_used: int, confirmed_used: int},
     *   slot_duration: int,
     *   intelligent: bool,
     *   locations: list<string>,
     *   can_request: bool
     * }
     */
    public static function forParticipant(Participation $participation, array $meeting, int $eventId): array
    {
        $setting = EventSetting::where('event_id', $eventId)->first();
        $enabled = self::meetingsTabEnabled($setting);
        $role = CommunicationCapabilities::roleFor($participation);
        $allowedRoles = self::allowedTargetRoles($meeting, $role);
        $limits = self::limitsFor($meeting, $role);
        $requestsUsed = self::outgoingRequestCount($eventId, $participation->id);
        $confirmedUsed = self::confirmedCount($eventId, $participation->id);

        return [
            'enabled' => $enabled,
            'role' => $role,
            'allowed_roles' => $allowedRoles,
            'restrictions' => [
                'requests' => $limits['requests'] === PHP_INT_MAX ? null : $limits['requests'],
                'confirmed' => $limits['confirmed'] === PHP_INT_MAX ? null : $limits['confirmed'],
                'requests_used' => $requestsUsed,
                'confirmed_used' => $confirmedUsed,
            ],
            'slot_duration' => self::slotDurationMinutes($meeting),
            'intelligent' => self::isIntelligent($meeting),
            'locations' => self::locations($meeting),
            'can_request' => $enabled
                && $allowedRoles !== []
                && self::canSendRequest($eventId, $participation, $meeting),
        ];
    }
}
