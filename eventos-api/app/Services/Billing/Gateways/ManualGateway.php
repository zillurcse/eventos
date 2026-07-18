<?php

namespace App\Services\Billing\Gateways;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\Contracts\PaymentGateway;

/**
 * No external calls — applies plan changes directly. Used in dev and for
 * manually-invoiced enterprise deals.
 */
class ManualGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function changePlan(Subscription $subscription, Plan $plan): void
    {
        $subscription->fill([
            'plan_id' => $plan->id,
            'gateway' => 'manual',
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
        // status is privileged (not $fillable).
        $subscription->forceFill(['status' => 'active'])->save();
    }

    public function cancel(Subscription $subscription): void
    {
        $subscription->fill([
            'cancel_at_period_end' => true,
            'canceled_at' => now(),
        ]);
        $subscription->forceFill(['status' => 'canceled'])->save();
    }
}
