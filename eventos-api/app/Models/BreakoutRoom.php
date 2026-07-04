<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A breakout room (Event Engagement › Breakout Rooms). Virtual collaboration
 * room scoped to an event; tenant isolation via BelongsToOrganization + RLS.
 * Extended moderation/collaboration/analytics state lives in `meta`.
 */
class BreakoutRoom extends Model
{
    use BelongsToOrganization, SoftDeletes, HasUuid;

    protected $guarded = [];

    /**
     * Room types that run as a one-way broadcast/stage: only hosts and invited
     * speakers publish, attendees watch. Every other type is participatory —
     * attendees get their own mic/camera so they can take part (round tables,
     * networking lounges, workshops, team/VIP rooms, …).
     */
    public const BROADCAST_TYPES = ['panel', 'ama', 'interview', 'sponsor_demo'];

    protected $casts = [
        'recording_enabled' => 'boolean',
        'capacity' => 'integer',
        'meta' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** Whether ordinary attendees may publish mic/camera in this room. */
    public function attendeesCanPublish(): bool
    {
        return ! in_array($this->type, self::BROADCAST_TYPES, true);
    }
}
