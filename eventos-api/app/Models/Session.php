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
     * Who may host / moderate this session — the single source of truth for the
     * attendee side (chat, Q&A, polls, and the Agora/Jitsi stage):
     *
     *   1. anyone on the stage: the session_speaker pivot (speaker, moderator,
     *      panelist or keynote);
     *   2. the event's own staff, i.e. a participation explicitly marked staff;
     *   3. an organizer — someone with an active Membership in the event's
     *      organization. They can step into any room of any event they run.
     *
     * (3) matters: an organizer attending their own event signs in as an
     * ordinary participation (role=attendee), so without this an event's own
     * owner could not take the stage or clear a spam message.
     */
    public function isModeratedBy(?Participation $participation): bool
    {
        if (! $participation) {
            return false;
        }

        if ($participation->role === 'staff') {
            return true;
        }

        if ($this->speakers()->where('participations.id', $participation->id)->exists()) {
            return true;
        }

        return $this->isOrganizer($participation);
    }

    /** Does this participation's person run the organization behind this event? */
    private function isOrganizer(Participation $participation): bool
    {
        $userId = $participation->contact?->user_id;

        if (! $userId) {
            return false;
        }

        // Identity lives on the migrator connection (it is not tenant-scoped).
        return Membership::on('pgsql_admin')
            ->where('organization_id', $this->organization_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    /** Questions wait for host approval before attendees see them. */
    public function qaModerated(): bool
    {
        return (bool) ($this->meta['qa_moderation'] ?? false);
    }
}
