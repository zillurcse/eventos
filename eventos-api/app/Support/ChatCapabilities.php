<?php

namespace App\Support;

use App\Models\EventSetting;
use App\Models\Participation;

/**
 * Navigation & Menu › Modules › Chat, plus Communication › Chats (role matrix).
 */
class ChatCapabilities
{
    public const ROLES = ['attendee', 'speaker', 'exhibitor', 'sponsor'];

    /**
     * Is the Chat module switched on (admin › Navigation & Menu › Modules)?
     * Missing config defaults to on — same as an organizer who never opened
     * that screen.
     */
    public static function moduleEnabled(?EventSetting $setting): bool
    {
        $modules = is_array($setting?->navigation) ? ($setting->navigation['modules'] ?? null) : null;
        if (! is_array($modules)) {
            return true;
        }

        return ($modules['chat'] ?? true) !== false;
    }

    public static function abortUnlessEnabled(
        int $eventId,
        string $message = 'Chat is not enabled for this event.',
    ): void {
        $setting = EventSetting::where('event_id', $eventId)->first();
        abort_unless(self::moduleEnabled($setting), 403, $message);
    }

    /**
     * Roles the participant may START a chat with, from event_settings.chat.
     * A missing matrix (or missing row) means unrestricted.
     *
     * @return list<string>
     */
    public static function allowedTargetRoles(?EventSetting $setting, string $fromRole): array
    {
        $fromRole = in_array($fromRole, self::ROLES, true) ? $fromRole : 'attendee';
        $matrix = is_array($setting?->chat) ? $setting->chat : [];
        $row = $matrix[$fromRole] ?? null;

        if (! is_array($row)) {
            return self::ROLES;
        }

        return array_values(array_filter(
            self::ROLES,
            fn (string $role) => (bool) ($row[$role] ?? true),
        ));
    }

    /**
     * Participant-facing payload for the event app (hide Chat CTAs the
     * organizer switched off for this caller's role).
     *
     * @return array{enabled: bool, role: string, allowed_roles: list<string>}
     */
    public static function forParticipant(Participation $me, ?EventSetting $setting): array
    {
        $role = CommunicationCapabilities::roleFor($me);
        $enabled = self::moduleEnabled($setting);

        return [
            'enabled' => $enabled,
            'role' => $role,
            'allowed_roles' => $enabled
                ? self::allowedTargetRoles($setting, $role)
                : [],
        ];
    }
}
