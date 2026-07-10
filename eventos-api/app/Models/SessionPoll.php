<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A live poll for a session (organizer-authored). Options are a jsonb list of
 * { id, text }; tallies come from session_poll_votes. Tenant-isolated via
 * BelongsToOrganization + RLS.
 */
class SessionPoll extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'is_active' => 'boolean',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(SessionPollVote::class);
    }
}
