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

    /** Who may reply to a Q&A question — see qaAnswerPolicy(). */
    public const QA_ANSWER_ORGANIZERS = 'organizers';

    public const QA_ANSWER_HOSTS = 'hosts';

    public const QA_ANSWER_EVERYONE = 'everyone';

    public const QA_ANSWER_POLICIES = [self::QA_ANSWER_ORGANIZERS, self::QA_ANSWER_HOSTS, self::QA_ANSWER_EVERYONE];

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

    /**
     * Who may post a reply under a Q&A question (sessions.meta.qa_answer_policy,
     * set by the organizer in Showcase › Sessions › Engagement Options). A
     * widening ladder:
     *
     *   organizers — event staff / organizers only. For a session where an
     *                answer is a statement from the event itself.
     *   hosts      — them plus this session's speakers (the default: the person
     *                on stage is the one being asked).
     *   everyone   — anyone in the room, so attendees can help each other. An
     *                attendee's reply still goes through pre-moderation when
     *                qa_moderation is on.
     *
     * An organizer is in every tier by construction: they run the event and
     * already moderate this thread, so the setting governs who *else* may answer.
     */
    public function qaAnswerPolicy(): string
    {
        $policy = $this->meta['qa_answer_policy'] ?? self::QA_ANSWER_HOSTS;

        return in_array($policy, self::QA_ANSWER_POLICIES, true) ? $policy : self::QA_ANSWER_HOSTS;
    }

    public function canAnswerQa(?Participation $participation): bool
    {
        if (! $participation) {
            return false;
        }

        $role = $this->qaRoleOf($participation);

        return match ($this->qaAnswerPolicy()) {
            self::QA_ANSWER_ORGANIZERS => $role === SessionMessage::ROLE_ORGANIZER,
            self::QA_ANSWER_EVERYONE => true,
            default => in_array($role, [SessionMessage::ROLE_ORGANIZER, SessionMessage::ROLE_SPEAKER], true),
        };
    }

    /**
     * The badge a message from this person carries. Speaker is checked before
     * organizer: someone who is both — an organizer presenting their own session
     * — is answering as the person on stage, and that is what the room needs to
     * see. Snapshotted onto the row at write time, never recomputed.
     */
    public function qaRoleOf(?Participation $participation): string
    {
        if (! $participation) {
            return SessionMessage::ROLE_ATTENDEE;
        }

        if ($this->speakers()->where('participations.id', $participation->id)->exists()) {
            return SessionMessage::ROLE_SPEAKER;
        }

        if ($participation->role === 'staff' || $this->isOrganizer($participation)) {
            return SessionMessage::ROLE_ORGANIZER;
        }

        return SessionMessage::ROLE_ATTENDEE;
    }
}
