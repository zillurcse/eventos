<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasDynamicFields;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A contact's involvement in one event, with a role (architecture §6.4).
 */
class Participation extends Model
{
    use BelongsToOrganization, SoftDeletes, HasUuid, Auditable, HasDynamicFields;

    // `role` (privilege) and `organization_id` (tenant) are intentionally absent:
    // organization_id is set by the BelongsToOrganization creating hook, and
    // role is set via forceFill at the trusted create sites only.
    protected $fillable = [
        'event_id', 'contact_id', 'status', 'ticket_id', 'registration_submission_id',
        'profile_data', 'networking_opt_in', 'checked_in_at', 'meta',
    ];

    protected $casts = [
        'profile_data' => 'array',
        'meta' => 'array',
        'networking_opt_in' => 'boolean',
        'checked_in_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function speakingSessions(): BelongsToMany
    {
        return $this->belongsToMany(Session::class, 'session_speaker')
            ->withPivot('role', 'sort_order')
            ->withTimestamps();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ParticipationGroup::class, 'participation_group_member', 'participation_id', 'group_id');
    }

    public function scopeAttendees($q)
    {
        return $q->where('role', 'attendee');
    }

    public function scopeSpeakers($q)
    {
        return $q->where('role', 'speaker');
    }
}
