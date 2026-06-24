<?php

namespace App\Services\Billing\Contracts;

use App\Models\Plan;
use App\Models\Subscription;

/**
 * Billing gateway driver (architecture §6.2, §10.5). Stripe (Cashier) and the
 * local gateways (bKash / Nagad / SSLCOMMERZ) implement this; ManualGateway is
 * the no-external-call default used in development.
 */
interface PaymentGateway
{
    public function name(): string;

    public function changePlan(Subscription $subscription, Plan $plan): void;

    public function cancel(Subscription $subscription): void;
}
