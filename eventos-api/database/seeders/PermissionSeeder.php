<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Atomic permission catalog (architecture §6.1). `platform.*` are control-plane
 * permissions (Super Admin); the rest are tenant capabilities.
 */
class PermissionSeeder extends Seeder
{
    public const PLATFORM = [
        'platform.organizations.manage', 'platform.plans.manage', 'platform.billing.manage',
        'platform.analytics.view', 'platform.settings.manage', 'platform.users.manage',
        'platform.audit.view',
    ];

    public const TENANT = [
        'events.manage', 'events.view',
        'attendees.manage', 'speakers.manage', 'exhibitors.manage',
        'sessions.manage', 'venues.manage',
        'ticketing.manage', 'checkin.manage',
        'forms.manage', 'email.manage',
        'networking.manage', 'feed.moderate', 'announcements.manage',
        'documents.manage', 'surveys.manage',
        'notifications.manage', 'analytics.view', 'settings.manage', 'members.manage',
    ];

    public function run(): void
    {
        foreach (self::PLATFORM as $key) {
            Permission::updateOrCreate(['key' => $key], ['group' => 'platform']);
        }

        foreach (self::TENANT as $key) {
            Permission::updateOrCreate(['key' => $key], ['group' => explode('.', $key)[0]]);
        }
    }
}
