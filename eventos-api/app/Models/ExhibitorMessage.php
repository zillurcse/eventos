<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single message in an exhibitor "Contact" thread. Sent by the attendee
 * (sender_side=attendee, sender_participation_id) or the exhibitor side
 * (sender_side=exhibitor, sender_member_id).
 */
class ExhibitorMessage extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ExhibitorConversation::class, 'conversation_id');
    }

    public function senderParticipation(): BelongsTo
    {
        return $this->belongsTo(Participation::class, 'sender_participation_id');
    }

    public function senderMember(): BelongsTo
    {
        return $this->belongsTo(ExhibitorMember::class, 'sender_member_id');
    }
}
