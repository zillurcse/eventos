<?php

namespace App\Events;

use App\Models\ChatMessage;
use App\Models\Event;
use App\Models\Participation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time chat fan-out over Reverb. Broadcast to BOTH sides' personal
 * channels (`event.{event uuid}.chat.{participation uuid}`) so the recipient
 * gets the message live and the sender's other tabs stay in sync. Channels are
 * keyed on unguessable uuids, mirroring the feed channel convention.
 */
class NewChatMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatMessage $message,
        public string $conversationUuid,
        public int $recipientParticipationId,
    ) {}

    public function broadcastOn(): array
    {
        $eventUuid = Event::find($this->message->event_id)?->uuid;
        $uuids = Participation::whereIn('id', [
            $this->message->sender_participation_id,
            $this->recipientParticipationId,
        ])->pluck('uuid');

        return $uuids
            ->map(fn ($u) => new Channel("event.{$eventUuid}.chat.{$u}"))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'chat.message.created';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationUuid,
            'message' => [
                'id' => $this->message->uuid,
                'body' => $this->message->body,
                'attachments' => ($this->message->meta ?? [])['attachments'] ?? [],
                // Ready-made inbox preview line ("📷 Photo" for attachment-only).
                'preview' => \App\Http\Controllers\Api\V1\ChatController::previewLabel($this->message),
                'sender' => Participation::find($this->message->sender_participation_id)?->uuid,
                'created_at' => $this->message->created_at?->toIso8601String(),
            ],
        ];
    }
}
