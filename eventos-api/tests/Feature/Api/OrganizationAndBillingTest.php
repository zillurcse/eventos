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

    public function test_requesting_a_plan_validates_the_slug(): void
    {
        $this->actingAsOrganizer();

        $this->postJson('/api/v1/subscription/change-request', ['plan' => 'does-not-exist'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }

    public function test_requesting_a_plan_creates_a_pending_request_without_switching(): void
    {
        $this->actingAsOrganizer(); // Free plan

        $this->postJson('/api/v1/subscription/change-request', ['plan' => 'pro'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.requested_plan.slug', 'pro');

        // The subscription is NOT switched — it waits for admin approval.
        $this->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.plan.slug', 'free');

        $this->getJson('/api/v1/subscription/change-request')
            ->assertOk()
            ->assertJsonPath('data.requested_plan.slug', 'pro');
    }

    public function test_requesting_the_current_plan_is_rejected(): void
    {
        $this->actingAsOrganizer(); // already on Free

        $this->postJson('/api/v1/subscription/change-request', ['plan' => 'free'])
            ->assertStatus(422);
    }

    public function test_a_pending_request_can_be_withdrawn(): void
    {
        $this->actingAsOrganizer();

        $this->postJson('/api/v1/subscription/change-request', ['plan' => 'pro'])->assertCreated();

        $this->deleteJson('/api/v1/subscription/change-request')->assertOk();

        $this->getJson('/api/v1/subscription/change-request')
            ->assertOk()
            ->assertJsonPath('data', null);
    }
}
