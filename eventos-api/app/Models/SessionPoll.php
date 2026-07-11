<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A live poll for a session, authored by the organizer in the admin or by the
 * host from the watch page. Options are a jsonb list of { id, text }; tallies
 * come from session_poll_votes.
 *
 * Lifecycle: draft (written, attendees can't see it) → live (open for votes) →
 * closed (voting over, results final). `show_results` decides whether attendees
 * see the tally while voting is still open, or only once it closes.
 */
class SessionPoll extends Model
{
    use BelongsToOrganization, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_LIVE = 'live';

    public const STATUS_CLOSED = 'closed';

    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'show_results' => 'boolean',
        'published_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(SessionPollVote::class);
    }

    /** Open for voting. */
    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    /** Attendees see live and closed polls — never drafts. */
    public function isVisibleToAttendees(): bool
    {
        return in_array($this->status, [self::STATUS_LIVE, self::STATUS_CLOSED], true);
    }

    /** The tally goes public once voting closes, or earlier if the host allows it. */
    public function resultsVisible(): bool
    {
        return $this->status === self::STATUS_CLOSED || $this->show_results;
    }
}
