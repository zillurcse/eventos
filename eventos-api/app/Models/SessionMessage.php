<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A live-session side-panel message — group Chat (kind=chat), a Q&A question
 * (kind=question, with upvotes) or a reply to one (kind=answer, parent_id set).
 * Hosts can hide, pin, answer or delete these; deletes are soft so a mis-click
 * is recoverable. Tenant-isolated via BelongsToOrganization + RLS.
 */
class SessionMessage extends Model
{
    use BelongsToOrganization, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_REJECTED = 'rejected';

    public const KIND_CHAT = 'chat';

    public const KIND_QUESTION = 'question';

    public const KIND_ANSWER = 'answer';

    /** Author roles, snapshotted on write — see the qa-replies migration. */
    public const ROLE_ORGANIZER = 'organizer';

    public const ROLE_SPEAKER = 'speaker';

    public const ROLE_ATTENDEE = 'attendee';

    protected $guarded = [];

    protected $casts = [
        'upvotes' => 'integer',
        'is_answered' => 'boolean',
        'is_hidden' => 'boolean',
        'is_pinned' => 'boolean',
        'answered_at' => 'datetime',
        'moderated_at' => 'datetime',
        'meta' => 'array',
    ];

    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class);
    }

    /** The question this row answers (kind=answer only). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Answers posted under this question. */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->where('kind', self::KIND_ANSWER);
    }

    /**
     * An answer from someone on the stage or running the event, as opposed to
     * one attendee helping out another. It carries the badge, and it's what
     * marks the question answered.
     */
    public function isOfficial(): bool
    {
        return in_array($this->author_role, [self::ROLE_ORGANIZER, self::ROLE_SPEAKER], true);
    }

    /** The name to show: an organizer replying from the admin console has no
     *  participation, so their name is snapshotted onto the row instead. */
    public function authorName(): string
    {
        return ($this->meta['author_name'] ?? null)
            ?: ($this->participation?->contact?->fullName() ?: 'Attendee');
    }

    /**
     * What an ordinary attendee may see: published and not hidden — plus their
     * own pending messages, so a question doesn't appear to vanish while it
     * waits for the host's approval.
     */
    public function scopeVisibleTo(Builder $q, int $participationId): Builder
    {
        return $q->where(function (Builder $w) use ($participationId) {
            $w->where(fn (Builder $x) => $x->where('status', self::STATUS_PUBLISHED)->where('is_hidden', false))
                ->orWhere(fn (Builder $x) => $x->where('participation_id', $participationId)->where('status', self::STATUS_PENDING));
        });
    }
}
