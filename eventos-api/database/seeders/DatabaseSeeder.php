<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds platform reference data only (architecture §12). Tenant data is created
 * through the app, never seeded globally.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LocaleSeeder::class,
            FeatureSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            PlanSeeder::class,
        ]);

        // A platform Super Admin to sign in with (local dev only).
        User::updateOrCreate(
            ['email' => 'admin@eventos.test'],
            [
                'name' => 'Platform Admin',
                'password' => 'password',          // hashed by the cast
                'is_platform_staff' => true,
                'email_verified_at' => now(),
                'locale' => 'en',
                'timezone' => 'UTC',
            ],
        );
    }
}
