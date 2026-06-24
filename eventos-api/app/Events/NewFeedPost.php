<?php

namespace App\Events;

use App\Models\Event;
use App\Models\FeedPost;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time feed fan-out over Reverb (architecture §3.1). Broadcast NOW so the
 * post request fails loudly if broadcasting is misconfigured (dev signal).
 */
class NewFeedPost implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public FeedPost $post) {}

    public function broadcastOn(): array
    {
        // Channel keyed on the event's public uuid so SPA clients (which only
        // know the uuid) can subscribe.
        $uuid = Event::find($this->post->event_id)?->uuid;

        return [new Channel("event.{$uuid}.feed")];
    }

    public function broadcastAs(): string
    {
        return 'feed.post.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->post->uuid,
            'body' => $this->post->body,
            'author_type' => $this->post->author_type,
            'created_at' => $this->post->created_at?->toIso8601String(),
        ];
    }
}
