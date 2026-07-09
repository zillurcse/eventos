<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An attendee's meeting request against an exhibitor company. The exhibitor
 * admin assigns a member to attend (status requested → assigned → confirmed).
 */
class ExhibitorMeetingRequest extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function exhibitor(): BelongsTo
    {
        return $this->belongsTo(Exhibitor::class);
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class);
    }

    public function assignedMember(): BelongsTo
    {
        return $this->belongsTo(ExhibitorMember::class, 'assigned_member_id');
    }
}
