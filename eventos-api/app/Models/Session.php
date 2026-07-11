<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function messages(): HasMany
    {
        return $this->hasMany(SessionMessage::class);
    }

    public function polls(): HasMany
    {
        return $this->hasMany(SessionPoll::class);
    }

    public function mutes(): HasMany
    {
        return $this->hasMany(SessionMute::class);
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

    /**
     * Who may moderate this session's chat / Q&A / polls: anyone on the stage
     * (the session_speaker pivot — speaker, moderator, panelist or keynote) plus
     * the organization's event staff, who can step into any room. The single
     * source of truth for every moderation check on the attendee side.
     */
    public function isModeratedBy(?Participation $participation): bool
    {
        if (! $participation) {
            return false;
        }

        if ($participation->role === 'staff') {
            return true;
        }

        return $this->speakers()
            ->where('participations.id', $participation->id)
            ->exists();
    }

    /** Questions wait for host approval before attendees see them. */
    public function qaModerated(): bool
    {
        return (bool) ($this->meta['qa_moderation'] ?? false);
    }
}
