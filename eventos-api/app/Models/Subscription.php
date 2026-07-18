<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use BelongsToOrganization;

    // plan_id is fillable ONLY because the plan-change flow validates it against
    // the plans table — if that validation is removed, move plan_id to forceFill
    // (entitlement-escalation vector). status is excluded (set via forceFill in
    // the billing gateway); organization_id is set by the trait's creating hook.
    protected $fillable = [
        'plan_id', 'gateway', 'gateway_subscription_id', 'quantity', 'trial_ends_at',
        'current_period_start', 'current_period_end', 'cancel_at_period_end',
        'canceled_at', 'ends_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }
}
