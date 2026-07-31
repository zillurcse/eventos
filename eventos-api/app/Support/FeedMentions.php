<?php

namespace App\Support;

use App\Models\Participation;
use App\Services\Notifications\NotificationService;

/**
 * Resolve + notify @mentions on feed posts/comments.
 */
class FeedMentions
{
    /**
     * @param  list<string>  $uuids
     * @return list<array{id: string, name: string, avatar_url: string|null}>
     */
    public static function resolve(int $eventId, array $uuids): array
    {
        $uuids = array_values(array_unique(array_filter(array_map('strval', $uuids))));
        if ($uuids === []) {
            return [];
        }

        $parts = Participation::with('contact')
            ->where('event_id', $eventId)
            ->where('role', 'attendee')
            ->whereIn('uuid', $uuids)
            ->get()
            ->keyBy('uuid');

        $out = [];
        foreach ($uuids as $uuid) {
            $p = $parts->get($uuid);
            if (! $p) {
                continue;
            }
            $name = trim(($p->contact?->first_name ?? '').' '.($p->contact?->last_name ?? ''));
            if ($name === '') {
                $name = 'Attendee';
            }
            $out[] = [
                'id' => $p->uuid,
                'name' => $name,
                'avatar_url' => $p->profile_data['avatar_url']
                    ?? $p->profile_data['image_url']
                    ?? ($p->meta['avatar_url'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{id: string, name: string, avatar_url?: string|null}>  $mentions
     */
    public static function notify(
        int $eventId,
        int $authorParticipationId,
        array $mentions,
        string $actionLabel,
    ): void {
        if ($mentions === []) {
            return;
        }

        $author = Participation::with('contact')->find($authorParticipationId);
        $authorName = trim(($author?->contact?->first_name ?? '').' '.($author?->contact?->last_name ?? ''));
        if ($authorName === '') {
            $authorName = 'Someone';
        }

        $uuids = array_column($mentions, 'id');
        $targets = Participation::query()
            ->where('event_id', $eventId)
            ->whereIn('uuid', $uuids)
            ->get(['id', 'uuid', 'organization_id']);

        $notifications = app(NotificationService::class);
        $channels = $notifications->channelsForEventAction($eventId, 'profile_view');
        if ($channels === []) {
            $channels = ['in_app'];
        }

        foreach ($targets as $target) {
            if ((int) $target->id === $authorParticipationId) {
                continue;
            }
            try {
                $notifications->notify(
                    'participation',
                    (int) $target->id,
                    (int) ($target->organization_id ?? $author?->organization_id),
                    $eventId,
                    'engagement.mention',
                    [
                        'title' => 'You were mentioned',
                        'body' => $authorName.' '.$actionLabel.'.',
                        'author_name' => $authorName,
                    ],
                    $channels,
                );
            } catch (\Throwable) {
                // Mentions must not fail the post/comment create path.
            }
        }
    }
}
