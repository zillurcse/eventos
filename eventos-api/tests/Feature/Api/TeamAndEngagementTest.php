<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Organizer team management + notifications, analytics and announcements. */
class TeamAndEngagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_assignable_roles_are_listed(): void
    {
        $this->actingAsOrganizer();

        $this->getJson('/api/v1/assignable-roles')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name']]]);
    }

    public function test_team_members_can_be_listed_and_invited(): void
    {
        $this->actingAsOrganizer();

        $this->getJson('/api/v1/members')->assertOk()->assertJsonStructure(['data']);

        $this->postJson('/api/v1/members', [
            'email' => 'teammate-'.uniqid().'@example.test',
            'name' => 'New Teammate',
            'status' => 'invited',
        ])->assertCreated()->assertJsonStructure(['data' => ['id', 'user', 'status']]);
    }

    public function test_member_invite_requires_an_email(): void
    {
        $this->actingAsOrganizer();

        $this->postJson('/api/v1/members', ['name' => 'No Email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_notifications_endpoints(): void
    {
        $this->actingAsOrganizer();

        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonStructure(['data']);
        $this->postJson('/api/v1/notifications/read-all')->assertOk();

        $this->getJson('/api/v1/notification-preferences')->assertOk();
        $this->putJson('/api/v1/notification-preferences', [
            'category' => 'sessions',
            'email' => true,
            'in_app' => true,
        ])->assertOk();
    }

    public function test_notification_template_can_be_created(): void
    {
        $this->actingAsOrganizer();

        $this->postJson('/api/v1/notification-templates', [
            'key' => 'reminder.'.uniqid(),
            'channel' => 'in_app',
            'subject' => 'Reminder',
            'body' => 'Your session starts soon.',
        ])->assertCreated()->assertJsonStructure(['data' => ['id', 'key', 'channel']]);
    }

    public function test_announcement_can_be_posted(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();

        $this->postJson('/api/v1/announcements', [
            'event' => $event['id'],
            'title' => 'Doors open at 9am',
            'body' => 'Welcome!',
        ])->assertCreated()->assertJsonStructure(['data' => ['id', 'title', 'status']]);
    }

    public function test_event_analytics_summary_and_rollup(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();

        $this->getJson("/api/v1/events/{$event['id']}/analytics")
            ->assertOk()
            ->assertJsonStructure(['data' => ['registrations', 'checked_in', 'sessions']]);

        $this->postJson("/api/v1/events/{$event['id']}/analytics/rollup")
            ->assertCreated()
            ->assertJsonStructure(['data']);
    }
}
