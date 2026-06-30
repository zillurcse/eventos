<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Event aggregate root (architecture §6.3). Times are UTC; `timezone` (IANA)
 * drives convert-on-display (§6.3.1).
 */
class Event extends Model
{
    use BelongsToOrganization, SoftDeletes, HasUuid, Auditable, HasTranslations;

    protected $guarded = [];

    public array $translatable = ['name', 'description'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'published_at' => 'datetime',
        'is_public' => 'boolean',
        'meta' => 'array',
    ];

    public function settings(): HasOne
    {
        return $this->hasOne(EventSetting::class);
    }

    public function coverFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'cover_file_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }

    public function participations(): HasMany
    {
        return $this->hasMany(Participation::class);
    }

    public function exhibitors(): HasMany
    {
        return $this->hasMany(Exhibitor::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    /** Resolve a session/event home zone: session → event → org → UTC (§6.3.1). */
    public function resolvedTimezone(): string
    {
        return $this->timezone ?: ($this->organization?->default_timezone ?? 'UTC');
    }
}
