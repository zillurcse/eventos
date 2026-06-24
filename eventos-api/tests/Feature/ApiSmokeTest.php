<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Read-only smoke tests over the running stack — assert the public surface and
 * that protected routes reject anonymous callers.
 */
class ApiSmokeTest extends TestCase
{
    public function test_health_endpoint_reports_ok(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.redis.status', 'ok');
    }

    public function test_plans_are_public(): void
    {
        $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'price_cents']]]);
    }

    public function test_tenant_route_requires_authentication(): void
    {
        $this->getJson('/api/v1/organization')->assertUnauthorized();
    }

    public function test_registration_validates_input(): void
    {
        $this->postJson('/api/v1/auth/register', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'organization_name']);
    }
}
