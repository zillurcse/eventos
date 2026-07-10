<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An attendee contest (Event Engagement › Contests). Either an "entry"
 * contest (attendees post to enter) or a "response" contest (attendees
 * comment on an organizer post). Tenant isolation via BelongsToOrganization +
 * RLS. Extended settings live in `meta`.
 */
class Contest extends Model
{
    use BelongsToOrganization, SoftDeletes, HasUuid;

    protected $guarded = [];

    protected $casts = [
        'character_limit' => 'integer',
        'points_for_entry' => 'integer',
        'points_for_response' => 'integer',
        'allow_photos' => 'boolean',
        'allow_videos' => 'boolean',
        'allow_selfie' => 'boolean',
        'winner_number' => 'integer',
        'winning_points' => 'integer',
        'equal_points_distribution' => 'boolean',
        'attach_mandatory' => 'boolean',
        'allow_multiple_entries' => 'boolean',
        'allow_moderate_entries' => 'boolean',
        'attendees_can_see_others_entries' => 'boolean',
        'attendees_can_see_other_comments' => 'boolean',
        'meta' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** Time-based lifecycle used to bucket the Contests list into tabs. */
    public function phase(): string
    {
        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return 'upcoming';
        }
        if ($this->ends_at && $now->gt($this->ends_at)) {
            return 'ended';
        }

        return 'ongoing';
    }
}
