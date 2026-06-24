<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;

/**
 * Resolves an organization's entitlements from its active subscription's plan
 * (architecture §6.2). Backs the Pennant feature definitions and the
 * `feature:` route middleware.
 */
class FeatureGate
{
    /** @var array<int, array<string,mixed>> per-request memo of feature maps */
    protected array $cache = [];

    public function plan(Organization $org): ?Plan
    {
        return Subscription::forOrganization($org->id)
            ->whereIn('status', ['active', 'trialing'])
            ->latest('id')
            ->first()
            ?->plan;
    }

    /** feature key => resolved value (true for modules, number for quotas). */
    public function features(Organization $org): array
    {
        return $this->cache[$org->id] ??= (function () use ($org) {
            $plan = $this->plan($org);

            if (! $plan) {
                return [];
            }

            return $plan->features()->get()
                ->mapWithKeys(fn ($f) => [$f->key => json_decode((string) $f->pivot->value, true)])
                ->all();
        })();
    }

    public function allows(Organization $org, string $featureKey): bool
    {
        return (bool) ($this->features($org)[$featureKey] ?? false);
    }

    /** Quota number for a feature/limit, or null when unlimited/undefined. */
    public function quota(Organization $org, string $key): ?int
    {
        $fromFeatures = $this->features($org)[$key] ?? null;
        if (is_numeric($fromFeatures)) {
            return (int) $fromFeatures;
        }

        $limits = $this->plan($org)?->limits ?? [];

        return isset($limits[$key]) && is_numeric($limits[$key]) ? (int) $limits[$key] : null;
    }

    public function flush(?int $orgId = null): void
    {
        $orgId === null ? $this->cache = [] : $this->cache[$orgId] = null;
    }
}
