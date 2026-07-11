<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A live-session side-panel message — group Chat (kind=chat) or a Q&A question
 * (kind=question, with upvotes). Hosts can hide, pin, answer or delete these;
 * deletes are soft so a mis-click is recoverable. Tenant-isolated via
 * BelongsToOrganization + RLS.
 */
class SessionMessage extends Model
{
    use BelongsToOrganization, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_REJECTED = 'rejected';

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

    /**
     * What an ordinary attendee may see: published and not hidden — plus their
     * own pending questions, so a question doesn't appear to vanish while it
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
