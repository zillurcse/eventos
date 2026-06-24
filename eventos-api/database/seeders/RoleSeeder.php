<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * System roles seeded as org-NULL templates (architecture §6.1):
 *   super_admin → platform control plane (all platform.* permissions)
 *   owner       → full tenant access
 *   manager     → run events, no billing/member admin
 *   staff       → operate on-site (check-in, view)
 * Tenant provisioning clones/assigns these per organization.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = collect(PermissionSeeder::TENANT);

        $matrix = [
            ['name' => 'super_admin', 'scope' => 'platform', 'perms' => PermissionSeeder::PLATFORM],
            ['name' => 'owner',       'scope' => 'tenant',   'perms' => $tenant->all()],
            ['name' => 'manager',     'scope' => 'tenant',   'perms' => $tenant->reject(
                fn ($p) => in_array($p, ['settings.manage', 'members.manage', 'email.manage'], true)
            )->values()->all()],
            ['name' => 'staff',       'scope' => 'tenant',   'perms' => ['events.view', 'attendees.manage', 'checkin.manage', 'analytics.view']],
        ];

        foreach ($matrix as $row) {
            $role = Role::updateOrCreate(
                ['organization_id' => null, 'name' => $row['name']],
                ['scope' => $row['scope'], 'is_system' => true, 'description' => ucfirst($row['name']).' (system role)'],
            );

            $ids = Permission::whereIn('key', $row['perms'])->pluck('id');
            $role->permissions()->sync($ids);
        }
    }
}
