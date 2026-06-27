<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Exhibitors & sponsors: packages, exhibitor CRUD, products. */
class ExhibitorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_exhibitor_package_and_exhibitor_flow(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();
        $eventUuid = $event['id'];

        // Package.
        $packageId = $this->postJson('/api/v1/exhibitor-packages', [
            'event' => $eventUuid,
            'name' => 'Gold',
            'kind' => 'both',
            'price_cents' => 250000,
            'currency' => 'USD',
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/v1/exhibitor-packages?event={$eventUuid}")->assertOk();

        // Exhibitor (no admin email → no login provisioned).
        $exhibitorUuid = $this->postJson('/api/v1/exhibitors', [
            'event' => $eventUuid,
            'type' => 'exhibitor',
            'name' => 'Acme Corp',
            'package_id' => $packageId,
        ])->assertCreated()->assertJsonPath('data.name', 'Acme Corp')->json('data.id');

        $this->getJson("/api/v1/exhibitors/{$exhibitorUuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $exhibitorUuid);

        // Update.
        $this->patchJson("/api/v1/exhibitors/{$exhibitorUuid}", ['status' => 'suspended'])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        // Product.
        $this->postJson("/api/v1/exhibitors/{$exhibitorUuid}/products", [
            'name' => 'Widget 3000',
        ])->assertCreated();

        $this->getJson("/api/v1/exhibitors/{$exhibitorUuid}/products")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_exhibitor_requires_a_name_and_event(): void
    {
        $this->actingAsOrganizer();

        $this->postJson('/api/v1/exhibitors', ['type' => 'sponsor'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event', 'name']);
    }

    public function test_exhibitor_index_can_filter_by_type(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();

        $this->getJson("/api/v1/exhibitors?event={$event['id']}&type=sponsor")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
