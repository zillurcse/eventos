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

    protected $guarded = [];

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
