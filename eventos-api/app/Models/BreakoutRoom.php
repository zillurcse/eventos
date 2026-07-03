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
}
