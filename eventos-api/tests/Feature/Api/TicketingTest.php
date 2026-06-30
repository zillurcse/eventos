<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Ticketing & check-in: ticket types, discount codes, check-in stations. */
class TicketingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ticket_types_can_be_created_and_listed(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();

        $this->postJson('/api/v1/ticket-types', [
            'event' => $event['id'],
            'name' => 'General Admission',
            'price_cents' => 5000,
            'currency' => 'USD',
            'quantity' => 100,
        ])->assertCreated()->assertJsonPath('data.name', 'General Admission');

        $this->getJson("/api/v1/ticket-types?event={$event['id']}")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_ticket_type_requires_a_name_and_event(): void
    {
        $this->actingAsOrganizer();

        $this->postJson('/api/v1/ticket-types', ['price_cents' => 1000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event', 'name']);
    }

    public function test_discount_code_can_be_created(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();

        $this->postJson('/api/v1/discount-codes', [
            'event' => $event['id'],
            'code' => 'EARLY'.strtoupper(substr(uniqid(), -5)),
            'type' => 'percent',
            'value' => 20,
            'max_uses' => 50,
        ])->assertCreated()->assertJsonStructure(['data' => ['code', 'type', 'value']]);
    }

    public function test_check_in_station_can_be_created_and_listed(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();

        $this->postJson('/api/v1/check-in-stations', [
            'event' => $event['id'],
            'name' => 'Front Door',
            'type' => 'entrance',
        ])->assertCreated()->assertJsonPath('data.name', 'Front Door');

        $this->getJson("/api/v1/check-in-stations?event={$event['id']}")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
