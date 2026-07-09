<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A "Contact" thread between an attendee (participation) and an exhibitor
 * company. One row per (event, exhibitor, attendee). The exhibitor admin may
 * assign a member to handle it.
 */
class ExhibitorConversation extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'last_message_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(ExhibitorMessage::class, 'conversation_id');
    }

    public function exhibitor(): BelongsTo
    {
        return $this->belongsTo(Exhibitor::class);
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class);
    }

    public function assignedMember(): BelongsTo
    {
        return $this->belongsTo(ExhibitorMember::class, 'assigned_member_id');
    }
}
