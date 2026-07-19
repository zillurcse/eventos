<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An organizer's request to move to another plan, awaiting super-admin approval.
 * `status` is privileged (approve/reject) — set via forceFill, never mass-assigned.
 */
class PlanChangeRequest extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'subscription_id', 'current_plan_id', 'requested_plan_id', 'note',
        'requested_by',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'requested_plan_id');
    }

    public function currentPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'current_plan_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
