<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventSetting;
use App\Models\Exhibitor;
use App\Models\ExhibitorMember;
use App\Models\Participation;

/**
 * Communication → Functionality (event_settings.communication).
 *
 * Organizer matrix of which roles may perform feed / session actions, plus
 * moderation switches and the ordered feed-tab rail. Missing config means
 * "everything allowed / no extra moderation" — same as an organizer who has
 * never opened the screen.
 */
class CommunicationCapabilities
{
    public const ROLES = ['attendee', 'speaker', 'exhibitor', 'sponsor'];

    public const OPERATIONS = [
        'create_feed_text',
        'create_feed_image',
        'create_feed_video',
        'create_feed_polls',
        'create_feed_offering',
        'create_feed_looking_for',
        'comment_feed_post',
        'feed_post_likes',
        'create_agenda_post',
        'create_agenda_qa',
        'create_agenda_polls',
        'vote_feed_polls',
        'vote_agenda_polls',
    ];

    /** Feed post `type` → functionality operation. */
    public const FEED_TYPE_OPS = [
        'text' => 'create_feed_text',
        'image' => 'create_feed_image',
        'video' => 'create_feed_video',
        'pdf' => 'create_feed_text',
        'poll' => 'create_feed_polls',
        'offering' => 'create_feed_offering',
        'looking_for' => 'create_feed_looking_for',
    ];

    /**
     * Role vocabulary used by the Functionality matrix for this participation.
     * Unknown / staff roles fall back to attendee so the matrix still applies.
     */
    public static function roleFor(Participation $participation): string
    {
        $role = $participation->role ?? 'attendee';

        if ($role === 'speaker') {
            return 'speaker';
        }

        if ($role === 'sponsor') {
            return 'sponsor';
        }

        // Booth staff may be stored as partner_member or exhibitor; sponsor vs
        // exhibitor follows the company's type (same split as BadgeAudience).
        if (in_array($role, ['partner_member', 'exhibitor'], true)) {
            $exhibitorId = ExhibitorMember::where('participation_id', $participation->id)->value('exhibitor_id')
                ?? ExhibitorMember::where('contact_id', $participation->contact_id)
                    ->whereHas('exhibitor', fn ($q) => $q->where('event_id', $participation->event_id))
                    ->value('exhibitor_id')
                ?? data_get($participation->meta, 'exhibitor_id');

            $type = $exhibitorId
                ? Exhibitor::where('id', $exhibitorId)->value('type')
                : null;

            return $type === 'sponsor' ? 'sponsor' : 'exhibitor';
        }

        return in_array($role, self::ROLES, true) ? $role : 'attendee';
    }

    public static function communication(?Event $event): array
    {
        if (! $event) {
            return [];
        }

        $settings = $event->relationLoaded('settings')
            ? $event->settings
            : $event->settings()->first();

        return is_array($settings?->communication) ? $settings->communication : [];
    }

    public static function fromSetting(?EventSetting $setting): array
    {
        return is_array($setting?->communication) ? $setting->communication : [];
    }

    /** Whether `$role` may perform `$operation`. Missing cells default to allowed. */
    public static function allows(array $communication, string $operation, string $role): bool
    {
        $row = $communication['functionality'][$operation] ?? null;
        if (! is_array($row)) {
            return true;
        }

        return (bool) ($row[$role] ?? true);
    }

    public static function allowsFor(Participation $participation, array $communication, string $operation): bool
    {
        return self::allows($communication, $operation, self::roleFor($participation));
    }

    public static function feedTypeOp(string $type): string
    {
        return self::FEED_TYPE_OPS[$type] ?? 'create_feed_text';
    }

    /**
     * Should a new feed post start as `pending`?
     *
     * Driven solely by Engagement › Manage Activity Feed (`event.meta.feed_moderation`).
     * When that switch is on, posts wait for organizer approval; otherwise they
     * publish immediately (auto-approved). Communication › Moderation covers
     * agenda Q&A separately — see agendaQuestionModerated().
     */
    public static function feedPostNeedsModeration(Event $event, string $type, array $communication): bool
    {
        return (bool) data_get($event->meta, 'feed_moderation', false);
    }

    /** Session Q&A pre-moderation from Communication › Moderation. */
    public static function agendaQuestionModerated(array $communication): bool
    {
        return (bool) data_get($communication, 'moderation.agenda_question', false);
    }

    /**
     * Enabled feed tabs in organizer order. Communication › Allowed feed tabs
     * wins when configured; otherwise null so the caller can fall back to
     * Navigation › Allowed Feed Tabs.
     *
     * @return list<array{key: string, label: string}>|null
     */
    public static function enabledFeedTabs(array $communication): ?array
    {
        $tabs = $communication['feed_tabs'] ?? null;
        if (! is_array($tabs) || $tabs === []) {
            return null;
        }

        // Flat list from Functionality page: [{ key, label, enabled }, …]
        if (array_is_list($tabs)) {
            $out = [];
            foreach ($tabs as $tab) {
                if (! is_array($tab) || empty($tab['key']) || ($tab['enabled'] ?? true) === false) {
                    continue;
                }
                $out[] = [
                    'key' => (string) $tab['key'],
                    'label' => (string) ($tab['label'] ?? $tab['key']),
                ];
            }

            return $out === [] ? null : $out;
        }

        // Navigation-shaped { items: […] } if ever stored that way.
        $items = $tabs['items'] ?? null;
        if (! is_array($items)) {
            return null;
        }

        $out = [];
        foreach ($items as $tab) {
            if (! is_array($tab) || empty($tab['key']) || ! ($tab['enabled'] ?? false)) {
                continue;
            }
            $out[] = [
                'key' => (string) $tab['key'],
                'label' => (string) ($tab['label'] ?? $tab['key']),
            ];
        }

        return $out === [] ? null : $out;
    }

    /**
     * Participant-facing payload: role + allowed ops + moderation + feed tabs.
     *
     * @return array{
     *   role: string,
     *   operations: array<string, bool>,
     *   moderation: array{agenda_question: bool, create_post: bool, create_polls: bool},
     *   feed_tabs: list<array{key: string, label: string}>|null
     * }
     */
    public static function forParticipant(Participation $participation, array $communication): array
    {
        $role = self::roleFor($participation);
        $operations = [];
        foreach (self::OPERATIONS as $op) {
            $operations[$op] = self::allows($communication, $op, $role);
        }

        $mod = is_array($communication['moderation'] ?? null) ? $communication['moderation'] : [];

        return [
            'role' => $role,
            'operations' => $operations,
            'moderation' => [
                'agenda_question' => (bool) ($mod['agenda_question'] ?? false),
                'create_post' => (bool) ($mod['create_post'] ?? false),
                'create_polls' => (bool) ($mod['create_polls'] ?? false),
            ],
            'feed_tabs' => self::enabledFeedTabs($communication),
        ];
    }

    /** Abort 403 when the participant may not perform `$operation`. */
    public static function abortUnless(Participation $participation, array $communication, string $operation, string $message = 'This action is not allowed for your role.'): void
    {
        abort_unless(
            self::allowsFor($participation, $communication, $operation),
            403,
            $message,
        );
    }
}
