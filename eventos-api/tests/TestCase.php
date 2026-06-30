<?php

namespace Tests;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

/**
 * Base test case for the EventOS API suite.
 *
 * The schema is Postgres-specific (citext, partitioning, RLS) and the app uses
 * a dual-connection identity pattern: tenant data lives on the RLS-constrained
 * `pgsql` connection, while identity reads (who am I / which org) run on the
 * `pgsql_admin` (BYPASSRLS) connection. Tests therefore run against the live
 * Dockerised Postgres rather than a freshly-migrated sqlite DB.
 *
 * Helpers here provision a *committed* test organizer + organization on the
 * admin connection (idempotent, so it never grows the DB across runs). Because
 * that row is committed, `ResolveTenant`/`EnsurePermission` can see the
 * membership even when a test body's writes run inside a rolled-back
 * transaction (see the `DatabaseTransactions` trait on the mutating tests).
 */
abstract class TestCase extends BaseTestCase
{
    protected const ADMIN_CONN = 'pgsql_admin';

    protected const ORG_EMAIL = 'apitest-organizer@eventos.test';

    protected const ORG_SLUG = 'api-test-org';

    protected const PLATFORM_EMAIL = 'admin@eventos.test';

    /** The organization resolved for the active organizer (set by organizerUser()). */
    protected ?Organization $tenantOrg = null;

    protected function setUp(): void
    {
        parent::setUp();

        // A previous test may have left a tenant pinned on the singleton; start clean
        // so the BelongsToOrganization global scope doesn't leak across tests.
        app(TenantContext::class)->forget();
    }

    /**
     * Idempotently ensure a committed organizer + organization (owner role +
     * Free subscription) on the admin connection, mirroring what
     * OrganizationProvisioner builds during a real registration.
     */
    protected function organizerUser(): User
    {
        $conn = self::ADMIN_CONN;

        $user = User::on($conn)->where('email', self::ORG_EMAIL)->first();
        if (! $user) {
            $user = (new User)->setConnection($conn);
            $user->forceFill([
                'name' => 'API Test Organizer',
                'email' => self::ORG_EMAIL,
                'password' => 'password',          // hashed by the cast
                'email_verified_at' => now(),
                'locale' => 'en',
                'timezone' => 'UTC',
            ])->save();
        }

        $org = Organization::on($conn)->where('slug', self::ORG_SLUG)->first();
        if (! $org) {
            $org = (new Organization)->setConnection($conn);
            $org->forceFill([
                'name' => 'API Test Org',
                'slug' => self::ORG_SLUG,
                'status' => 'active',
                'owner_user_id' => $user->id,
                'default_currency' => 'USD',
                'default_timezone' => 'UTC',
                'default_locale' => 'en',
                'billing_email' => self::ORG_EMAIL,
            ])->save();
        }

        $hasSettings = OrganizationSetting::on($conn)->withoutGlobalScopes()
            ->where('organization_id', $org->id)->exists();
        if (! $hasSettings) {
            $settings = (new OrganizationSetting)->setConnection($conn);
            $settings->forceFill(['organization_id' => $org->id])->save();
        }

        $membership = Membership::on($conn)->withoutGlobalScopes()
            ->where('organization_id', $org->id)->where('user_id', $user->id)->first();
        if (! $membership) {
            $membership = (new Membership)->setConnection($conn);
            $membership->forceFill([
                'user_id' => $user->id,
                'organization_id' => $org->id,
                'status' => 'active',
                'joined_at' => now(),
            ])->save();
        }

        if ($owner = Role::on($conn)->whereNull('organization_id')->where('name', 'owner')->first()) {
            $membership->setConnection($conn)->roles()->sync([$owner->id]);
        }

        $hasSub = Subscription::on($conn)->withoutGlobalScopes()
            ->where('organization_id', $org->id)->exists();
        if (! $hasSub && ($free = Plan::on($conn)->where('slug', 'free')->first())) {
            $sub = (new Subscription)->setConnection($conn);
            $sub->forceFill([
                'organization_id' => $org->id,
                'plan_id' => $free->id,
                'status' => 'active',
                'gateway' => 'manual',
                'quantity' => 1,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ])->save();
        }

        $this->tenantOrg = $org;

        return $user;
    }

    /** Authenticate the test client as the organizer (owner = all tenant perms). */
    protected function actingAsOrganizer(): User
    {
        $user = $this->organizerUser();
        Sanctum::actingAs($user, ['tenant']);

        return $user;
    }

    /** Authenticate as the seeded platform super-admin. */
    protected function actingAsPlatform(): User
    {
        $admin = User::on(self::ADMIN_CONN)->where('email', self::PLATFORM_EMAIL)->firstOrFail();
        Sanctum::actingAs($admin, ['platform']);

        return $admin;
    }

    /**
     * Create a draft event through the API and return its decoded `data` payload.
     * Assumes the client is already authenticated as the organizer.
     */
    protected function createEvent(array $overrides = []): array
    {
        $payload = array_merge([
            'name' => 'Test Event '.uniqid(),
            'format' => 'venue',
            'timezone' => 'UTC',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'ends_at' => now()->addWeek()->addDay()->toIso8601String(),
        ], $overrides);

        return $this->postJson('/api/v1/events', $payload)
            ->assertCreated()
            ->json('data');
    }
}
