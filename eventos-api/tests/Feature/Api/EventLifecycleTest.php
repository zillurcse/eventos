<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Events aggregate root: full CRUD lifecycle + settings, overview, agenda, publish. */
class EventLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_events_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/events')->assertUnauthorized();
    }

    public function test_events_can_be_listed(): void
    {
        $this->actingAsOrganizer();

        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_creating_an_event_validates_input(): void
    {
        $this->actingAsOrganizer();

        $this->postJson('/api/v1/events', ['format' => 'spaceship'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'format']);
    }

    public function test_end_date_must_not_precede_start_date(): void
    {
        $this->actingAsOrganizer();

        $this->postJson('/api/v1/events', [
            'name' => 'Backwards Event',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'ends_at' => now()->toIso8601String(),
        ])->assertStatus(422)->assertJsonValidationErrors(['ends_at']);
    }

    public function test_event_creation_is_blocked_once_the_plan_quota_is_reached(): void
    {
        $this->actingAsOrganizer(); // Free plan: max_events = 1

        $this->createEvent(['name' => 'The Only One']); // first is allowed

        $this->postJson('/api/v1/events', ['name' => 'One Too Many', 'format' => 'venue'])
            ->assertStatus(403);
    }

    public function test_an_unlimited_plan_lifts_the_event_quota(): void
    {
        $this->actingAsOrganizer();
        $this->actAsPlan('enterprise'); // max_events = null (unlimited)

        $this->createEvent(['name' => 'First']);
        $this->createEvent(['name' => 'Second']); // would 403 on Free
    }

    public function test_full_event_lifecycle(): void
    {
        $this->actingAsOrganizer();

        // Create (starts as draft).
        $event = $this->createEvent(['name' => 'Lifecycle Conf']);
        $this->assertSame('draft', $event['status']);
        $uuid = $event['id'];

        // Show.
        $this->getJson("/api/v1/events/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $uuid)
            ->assertJsonPath('data.name', 'Lifecycle Conf');

        // Update.
        $this->patchJson("/api/v1/events/{$uuid}", ['name' => 'Lifecycle Conf 2026'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Lifecycle Conf 2026');

        // Overview (setup checklist + counts + credentials).
        $this->getJson("/api/v1/events/{$uuid}/overview")
            ->assertOk()
            ->assertJsonStructure(['data' => ['checklist', 'counts', 'completed', 'total', 'credentials']]);

        // Agenda (empty but well-formed).
        $this->getJson("/api/v1/events/{$uuid}/agenda")
            ->assertOk()
            ->assertJsonStructure(['data']);

        // Settings get + update.
        $this->getJson("/api/v1/events/{$uuid}/settings")
            ->assertOk()
            ->assertJsonStructure(['data' => ['theme', 'modules_enabled', 'branding']]);

        // NOTE: the subdomain is deliberately NOT settable here — the settings
        // endpoint unsets `domain` (EventController) because subdomain management
        // lives on the dedicated /events/{uuid}/domain endpoint. So we only assert
        // the theme round-trips.
        $this->putJson("/api/v1/events/{$uuid}/settings", [
            'theme' => ['primary' => '#6352e7', 'mode' => 'light'],
        ])
            ->assertOk()
            ->assertJsonPath('data.theme.primary', '#6352e7');

        // Publish.
        $this->postJson("/api/v1/events/{$uuid}/publish", ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        // Delete.
        $this->deleteJson("/api/v1/events/{$uuid}")
            ->assertOk()
            ->assertJsonPath('message', 'Event deleted.');

        $this->getJson("/api/v1/events/{$uuid}")->assertNotFound();
    }

    public function test_unknown_event_returns_404(): void
    {
        $this->actingAsOrganizer();

        $this->getJson('/api/v1/events/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }
}
