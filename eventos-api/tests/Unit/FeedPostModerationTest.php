<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Support\CommunicationCapabilities;
use PHPUnit\Framework\TestCase;

/** Engagement › Manage Activity Feed drives pending vs auto-published posts. */
class FeedPostModerationTest extends TestCase
{
    public function test_posts_need_moderation_when_manage_activity_feed_is_on(): void
    {
        $event = new Event(['meta' => ['feed_moderation' => true]]);

        $this->assertTrue(
            CommunicationCapabilities::feedPostNeedsModeration($event, 'text', [])
        );
        $this->assertTrue(
            CommunicationCapabilities::feedPostNeedsModeration($event, 'poll', [])
        );
    }

    public function test_posts_auto_approve_when_manage_activity_feed_is_off(): void
    {
        $event = new Event(['meta' => ['feed_moderation' => false]]);

        // Communication › Moderation flags must not hold posts when the
        // Manage Activity Feed switch is off.
        $communication = [
            'moderation' => [
                'create_post' => true,
                'create_polls' => true,
            ],
        ];

        $this->assertFalse(
            CommunicationCapabilities::feedPostNeedsModeration($event, 'text', $communication)
        );
        $this->assertFalse(
            CommunicationCapabilities::feedPostNeedsModeration($event, 'poll', $communication)
        );
    }

    public function test_missing_feed_moderation_defaults_to_auto_approve(): void
    {
        $event = new Event(['meta' => []]);

        $this->assertFalse(
            CommunicationCapabilities::feedPostNeedsModeration($event, 'text', [])
        );
    }
}
