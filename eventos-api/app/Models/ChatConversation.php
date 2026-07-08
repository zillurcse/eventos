<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A 1:1 chat thread between two event participations, normalized so
 * a_participation_id < b_participation_id (one row per pair per event).
 */
class ChatConversation extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'last_message_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function a(): BelongsTo
    {
        return $this->belongsTo(Participation::class, 'a_participation_id');
    }

    public function b(): BelongsTo
    {
        return $this->belongsTo(Participation::class, 'b_participation_id');
    }

    /** The participation id on the other side of the thread from $pid. */
    public function counterpartId(int $pid): int
    {
        return $this->a_participation_id === $pid
            ? $this->b_participation_id
            : $this->a_participation_id;
    }
}
