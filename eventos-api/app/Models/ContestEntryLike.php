<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One attendee's like on a contest entry (unique per entry+participation). */
class ContestEntryLike extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(ContestEntry::class, 'contest_entry_id');
    }
}
