<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Tenant context: current organization + subscription/billing. */
class OrganizationAndBillingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_organization_requires_authentication(): void
    {
        $this->getJson('/api/v1/organization')->assertUnauthorized();
    }

    public function test_current_organization_is_returned(): void
    {
        $this->actingAsOrganizer();

        $this->getJson('/api/v1/organization')
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name'], 'plan', 'features'])
            ->assertJsonPath('data.name', 'API Test Org');
    }

    public function test_current_subscription_is_returned(): void
    {
        $this->actingAsOrganizer();

        $this->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_changing_plan_validates_the_slug(): void
    {
        $this->actingAsOrganizer();

        $this->postJson('/api/v1/subscription/change', ['plan' => 'does-not-exist'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }

    public function test_changing_plan_switches_the_subscription(): void
    {
        $this->actingAsOrganizer();

        $this->postJson('/api/v1/subscription/change', ['plan' => 'free'])
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
