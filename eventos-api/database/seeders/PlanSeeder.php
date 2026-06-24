<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Default subscription catalog (architecture §6.2). limits JSONB feeds quota
 * metering; plan_features feeds Pennant gating.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $features = Feature::pluck('id', 'key');

        $plans = [
            [
                'name' => 'Free', 'price_cents' => 0, 'sort_order' => 1,
                'limits' => ['max_events' => 1, 'max_attendees' => 100, 'storage_gb' => 1, 'api_rate' => 60],
                'modules' => ['module.feed', 'module.ticketing'],
            ],
            [
                'name' => 'Pro', 'price_cents' => 4900, 'sort_order' => 2,
                'limits' => ['max_events' => 5, 'max_attendees' => 1000, 'storage_gb' => 10, 'api_rate' => 300],
                'modules' => ['module.feed', 'module.ticketing', 'module.networking', 'module.surveys', 'module.email_builder'],
            ],
            [
                'name' => 'Business', 'price_cents' => 19900, 'sort_order' => 3,
                'limits' => ['max_events' => 25, 'max_attendees' => 5000, 'storage_gb' => 100, 'api_rate' => 1000],
                'modules' => ['module.feed', 'module.ticketing', 'module.networking', 'module.surveys',
                    'module.email_builder', 'module.partners', 'module.analytics'],
            ],
            [
                'name' => 'Enterprise', 'price_cents' => 99900, 'sort_order' => 4,
                'limits' => ['max_events' => null, 'max_attendees' => null, 'storage_gb' => 1000, 'api_rate' => 5000],
                'modules' => array_keys($features->toArray()), // everything
            ],
        ];

        foreach ($plans as $p) {
            $plan = Plan::updateOrCreate(
                ['slug' => Str::slug($p['name'])],
                [
                    'name' => $p['name'],
                    'billing_interval' => 'month',
                    'price_cents' => $p['price_cents'],
                    'currency' => 'USD',
                    'trial_days' => $p['name'] === 'Free' ? 0 : 14,
                    'limits' => $p['limits'],
                    'is_public' => true,
                    'sort_order' => $p['sort_order'],
                ],
            );

            $attach = [];
            foreach ($p['modules'] as $key) {
                if (isset($features[$key])) {
                    $attach[$features[$key]] = ['value' => json_encode(true)];
                }
            }
            foreach (['quota.events' => 'max_events', 'quota.attendees' => 'max_attendees', 'quota.storage_gb' => 'storage_gb'] as $fkey => $lkey) {
                if (isset($features[$fkey])) {
                    $attach[$features[$fkey]] = ['value' => json_encode($p['limits'][$lkey] ?? null)];
                }
            }
            $plan->features()->sync($attach);
        }
    }
}
