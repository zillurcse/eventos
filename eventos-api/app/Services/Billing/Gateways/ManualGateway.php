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
        $subscription->update([
            'plan_id' => $plan->id,
            'gateway' => 'manual',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    public function cancel(Subscription $subscription): void
    {
        $subscription->update([
            'status' => 'canceled',
            'cancel_at_period_end' => true,
            'canceled_at' => now(),
        ]);
    }
}
