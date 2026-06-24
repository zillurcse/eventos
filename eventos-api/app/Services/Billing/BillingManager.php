<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\Contracts\PaymentGateway;
use App\Services\Billing\Gateways\ManualGateway;

/**
 * Resolves the billing driver and runs subscription operations. New drivers
 * (StripeGateway via Cashier, SslcommerzGateway, BkashGateway…) register here.
 */
class BillingManager
{
    public function gateway(string $name = 'manual'): PaymentGateway
    {
        return match ($name) {
            'manual' => new ManualGateway(),
            // 'stripe'     => app(StripeGateway::class),     // needs Cashier + API keys
            // 'sslcommerz' => app(SslcommerzGateway::class), // local gateway driver
            default => new ManualGateway(),
        };
    }

    public function changePlan(Subscription $subscription, Plan $plan, string $gateway = 'manual'): Subscription
    {
        $this->gateway($gateway)->changePlan($subscription, $plan);

        return $subscription->fresh('plan');
    }

    public function cancel(Subscription $subscription, string $gateway = 'manual'): Subscription
    {
        $this->gateway($gateway)->cancel($subscription);

        return $subscription->fresh();
    }
}
