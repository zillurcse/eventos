<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One awarded gamification point event (append-only ledger row).
 */
class GamificationPointEvent extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected $casts = [
        'points' => 'integer',
        'meta' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class);
    }
}
