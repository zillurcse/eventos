<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

/**
 * Gateable feature catalog (architecture §6.2). Plans grant these via
 * plan_features; Pennant resolves them at runtime in a later phase.
 */
class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            // boolean module toggles
            ['key' => 'module.networking',   'name' => 'Networking & Meetings', 'type' => 'boolean'],
            ['key' => 'module.feed',         'name' => 'Event Feed',            'type' => 'boolean'],
            ['key' => 'module.partners',     'name' => 'Exhibitors & Sponsors', 'type' => 'boolean'],
            ['key' => 'module.surveys',      'name' => 'Surveys & Polls',       'type' => 'boolean'],
            ['key' => 'module.ticketing',    'name' => 'Ticketing',             'type' => 'boolean'],
            ['key' => 'module.email_builder','name' => 'Email Builder',         'type' => 'boolean'],
            ['key' => 'module.analytics',    'name' => 'Advanced Analytics',    'type' => 'boolean'],
            ['key' => 'module.api_access',   'name' => 'API Access',            'type' => 'boolean'],
            // quotas / metered
            ['key' => 'quota.events',        'name' => 'Max Events',            'type' => 'quota'],
            ['key' => 'quota.attendees',     'name' => 'Attendees per Event',   'type' => 'quota'],
            ['key' => 'quota.storage_gb',    'name' => 'Storage (GB)',          'type' => 'quota'],
            ['key' => 'metered.emails',      'name' => 'Emails Sent',           'type' => 'metered'],
        ];

        foreach ($features as $f) {
            Feature::updateOrCreate(['key' => $f['key']], $f);
        }
    }
}
