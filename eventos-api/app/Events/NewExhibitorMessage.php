<?php

namespace App\Events;

use App\Models\Event;
use App\Models\ExhibitorMessage;
use App\Models\Participation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time delivery of an exhibitor "Contact" reply to the attendee, over
 * Reverb. Broadcast to the attendee's personal channel
 * (`event.{event uuid}.exhibitor-contact.{participation uuid}`), keyed on
 * unguessable uuids like the chat/feed channels. Only the exhibitor→attendee
 * direction is broadcast (the exhibitor-admin SPA has no Echo client; it polls).
 */
class NewExhibitorMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ExhibitorMessage $message,
        public string $conversationUuid,
        public string $exhibitorUuid,
        public int $recipientParticipationId,
    ) {}

    public function broadcastOn(): array
    {
        $eventUuid = Event::find($this->message->event_id)?->uuid;
        $recipientUuid = Participation::find($this->recipientParticipationId)?->uuid;

        return [new Channel("event.{$eventUuid}.exhibitor-contact.{$recipientUuid}")];
    }

    public function broadcastAs(): string
    {
        return 'exhibitor.contact.message';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationUuid,
            'exhibitor_id' => $this->exhibitorUuid,
            'message' => [
                'id' => $this->message->uuid,
                'body' => $this->message->body,
                'side' => $this->message->sender_side,
                'mine' => false, // it's the exhibitor's reply, i.e. not the attendee's
                'created_at' => $this->message->created_at?->toIso8601String(),
            ],
        ];
    }
}
