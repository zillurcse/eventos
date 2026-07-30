<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attendee's rating for one speaker. Unique per speaker+participation so a
 * later rating updates the same row instead of double-counting.
 */
class SpeakerRating extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $guarded = [];

    protected $casts = [
        'rated_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function speakerParticipation(): BelongsTo
    {
        return $this->belongsTo(Participation::class, 'speaker_participation_id');
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class);
    }
}
