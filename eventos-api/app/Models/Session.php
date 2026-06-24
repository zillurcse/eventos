<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Session/talk (architecture §6.3, §10.4). Speakers are participations linked
 * via the session_speaker pivot.
 */
class Session extends Model
{
    use BelongsToOrganization, SoftDeletes, HasUuid, Auditable, HasTranslations;

    protected $guarded = [];

    public array $translatable = ['title', 'description'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'meta' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(Participation::class, 'session_speaker')
            ->withPivot('role', 'sort_order')
            ->withTimestamps();
    }

    /**
     * Home zone for display: session → event → organization → UTC (§6.3.1).
     * Times themselves are always stored/returned as UTC instants.
     */
    public function resolvedTimezone(): string
    {
        return $this->timezone
            ?: ($this->event?->resolvedTimezone() ?? 'UTC');
    }
}
