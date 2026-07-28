<?php

namespace App\Support;

/**
 * Catalogue of Communication → Gamification point-scoring actions.
 *
 * Single source of truth for admin Point Scoring and the runtime scorer.
 * Keys match the `{ action_key => points }` map stored on `gamifications.scores`.
 */
class GamificationActions
{
    /**
     * @return list<array{key: string, label: string, column: 'left'|'right', once: bool}>
     */
    public static function all(): array
    {
        return [
            // Left column
            ['key' => 'create_account', 'label' => 'Create account', 'column' => 'left', 'once' => true],
            ['key' => 'complete_onboarding', 'label' => 'Complete onboarding', 'column' => 'left', 'once' => true],
            ['key' => 'attend_welcoming_video', 'label' => 'Attend welcoming video', 'column' => 'left', 'once' => true],
            ['key' => 'create_feed_text_post', 'label' => 'Create feed text post', 'column' => 'left', 'once' => false],
            ['key' => 'create_feed_image_post', 'label' => 'Create feed image post', 'column' => 'left', 'once' => false],
            ['key' => 'create_feed_video_post', 'label' => 'Create feed video post', 'column' => 'left', 'once' => false],
            ['key' => 'create_feed_polls_post', 'label' => 'Create feed polls post', 'column' => 'left', 'once' => false],
            ['key' => 'create_feed_offering_post', 'label' => 'Create feed offering post', 'column' => 'left', 'once' => false],
            ['key' => 'create_feed_looking_for_post', 'label' => 'Create feed looking for post', 'column' => 'left', 'once' => false],
            ['key' => 'comment_feed_post', 'label' => 'Comment feed post', 'column' => 'left', 'once' => false],
            ['key' => 'feed_post_likes', 'label' => 'Feed post likes', 'column' => 'left', 'once' => false],
            ['key' => 'vote_feed_polls', 'label' => 'Vote feed Polls', 'column' => 'left', 'once' => false],
            ['key' => 'sessions_agenda_chat', 'label' => 'Sessions/ Agenda chat', 'column' => 'left', 'once' => false],
            ['key' => 'create_sessions_agenda_qa', 'label' => 'Create sessions/ Agenda Q&A', 'column' => 'left', 'once' => false],
            ['key' => 'create_sessions_agenda_polls', 'label' => 'Create sessions/ Agenda polls', 'column' => 'left', 'once' => false],
            ['key' => 'vote_sessions_agenda_polls', 'label' => 'Vote sessions/ Agenda polls', 'column' => 'left', 'once' => false],
            ['key' => 'rate_session', 'label' => 'Rate session', 'column' => 'left', 'once' => false],
            ['key' => 'attend_lounge_meeting', 'label' => 'Attend lounge meeting', 'column' => 'left', 'once' => false],
            ['key' => 'lounge_meeting_feedback', 'label' => 'Lounge meeting feedback', 'column' => 'left', 'once' => false],

            // Right column
            ['key' => 'attend_breakout_rooms_meeting', 'label' => 'Attend breakout rooms meeting', 'column' => 'right', 'once' => false],
            ['key' => 'breakout_rooms_meeting_feedback', 'label' => 'Breakout rooms meeting feedback', 'column' => 'right', 'once' => false],
            ['key' => 'visit_exhibitor_profile', 'label' => 'Visit exhibitor profile', 'column' => 'right', 'once' => false],
            ['key' => 'visit_exhibitor_social_media', 'label' => 'Visit exhibitor social media', 'column' => 'right', 'once' => false],
            ['key' => 'rate_exhibitor', 'label' => 'Rate exhibitor', 'column' => 'right', 'once' => false],
            ['key' => 'chat_with_exhibitor_representative', 'label' => 'Chat with exhibitor representative', 'column' => 'right', 'once' => false],
            ['key' => 'meet_exhibitor_representative', 'label' => 'Meet exhibitor representative', 'column' => 'right', 'once' => false],
            ['key' => 'exhibitor_representative_meeting_feedback', 'label' => 'Exhibitor representative meeting feedback', 'column' => 'right', 'once' => false],
            ['key' => 'attend_exhibitor_displayed_videos', 'label' => 'Attend exhibitor displayed videos', 'column' => 'right', 'once' => false],
            ['key' => 'attend_exhibitor_displayed_images', 'label' => 'Attend exhibitor displayed images', 'column' => 'right', 'once' => false],
            ['key' => 'visit_sponsor_profile', 'label' => 'Visit sponsor profile', 'column' => 'right', 'once' => false],
            ['key' => 'visit_sponsor_social_media', 'label' => 'Visit sponsor social media', 'column' => 'right', 'once' => false],
            ['key' => 'chat_with_sponsor_representative', 'label' => 'Chat with sponsor representative', 'column' => 'right', 'once' => false],
            ['key' => 'meet_sponsor_representative', 'label' => 'Meet sponsor representative', 'column' => 'right', 'once' => false],
            ['key' => 'exhibitor_sponsor_meeting_feedback', 'label' => 'Exhibitor sponsor meeting feedback', 'column' => 'right', 'once' => false],
            ['key' => 'attend_sponsor_displayed_videos', 'label' => 'Attend sponsor displayed videos', 'column' => 'right', 'once' => false],
            ['key' => 'chat_with_delegates', 'label' => 'Chat with delegates', 'column' => 'right', 'once' => false],
            ['key' => 'meet_delegates', 'label' => 'Meet delegates', 'column' => 'right', 'once' => false],
            ['key' => 'view_speakers_profile', 'label' => 'View speakers profile', 'column' => 'right', 'once' => false],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_column(self::all(), 'key');
    }

    public static function isValid(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    public static function isOnce(string $key): bool
    {
        foreach (self::all() as $action) {
            if ($action['key'] === $key) {
                return (bool) $action['once'];
            }
        }

        return false;
    }

    /** Feed post `type` → gamification action key. */
    public static function feedPostAction(string $type): string
    {
        return match ($type) {
            'image' => 'create_feed_image_post',
            'video' => 'create_feed_video_post',
            'poll' => 'create_feed_polls_post',
            'offering' => 'create_feed_offering_post',
            'looking_for' => 'create_feed_looking_for_post',
            default => 'create_feed_text_post',
        };
    }

    /** Default score map used when an organizer has never set scores. */
    public static function defaultScores(int $points = 1): array
    {
        $scores = [];
        foreach (self::keys() as $key) {
            $scores[$key] = $points;
        }

        return $scores;
    }
}
