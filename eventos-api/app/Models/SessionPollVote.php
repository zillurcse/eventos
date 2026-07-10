<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attendee's vote on a session poll (unique per poll+participation, so a
 * vote can be changed but not double-counted). Tenant-isolated via RLS.
 */
class SessionPollVote extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(SessionPoll::class, 'session_poll_id');
    }
}
