<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-event gamification config (Communication → Gamification). A singleton per
 * event holding the on/off toggle, the per-action points map and the award
 * block surfaced on the attendee login page.
 */
class Gamification extends Model
{
    use BelongsToOrganization, Auditable;

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'scores' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
