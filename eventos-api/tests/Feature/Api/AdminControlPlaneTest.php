<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Super-admin control plane (/admin/*) — platform-staff only, cross-tenant. */
class AdminControlPlaneTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/metrics')->assertUnauthorized();
    }

    public function test_non_platform_user_is_forbidden(): void
    {
        $this->actingAsOrganizer();

        $this->getJson('/api/v1/admin/metrics')->assertForbidden();
        $this->getJson('/api/v1/admin/organizations')->assertForbidden();
        $this->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_platform_metrics(): void
    {
        $this->actingAsPlatform();

        $this->getJson('/api/v1/admin/metrics')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'organizations', 'active_subscriptions', 'revenue_cents', 'events', 'attendees',
            ]]);
    }

    public function test_platform_can_list_organizations(): void
    {
        $this->actingAsPlatform();

        $this->getJson('/api/v1/admin/organizations')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_platform_can_list_and_create_plans(): void
    {
        $this->actingAsPlatform();

        $this->getJson('/api/v1/admin/plans')->assertOk()->assertJsonStructure(['data']);

        $this->postJson('/api/v1/admin/plans', [
            'name' => 'Test Plan '.uniqid(),
            'price_cents' => 9900,
            'billing_interval' => 'month',
        ])->assertCreated()->assertJsonStructure(['data' => ['id', 'name', 'slug']]);
    }

    public function test_platform_can_list_and_create_users(): void
    {
        $this->actingAsPlatform();

        $this->getJson('/api/v1/admin/users')->assertOk()->assertJsonStructure(['data']);

        $this->postJson('/api/v1/admin/users', [
            'name' => 'New Staff',
            'email' => 'staff-'.uniqid().'@eventos.test',
            'password' => 'password123',
            'is_platform_staff' => true,
        ])->assertCreated()->assertJsonStructure(['data' => ['id', 'email']]);
    }

    public function test_creating_a_plan_validates_input(): void
    {
        $this->actingAsPlatform();

        $this->postJson('/api/v1/admin/plans', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
