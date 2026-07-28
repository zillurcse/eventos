<?php

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Services\Notifications\NotificationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Profile › Notifications preferences gate delivery end-to-end.
 */
class NotificationPreferencesDeliveryTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        if ($this->tenantOrg) {
            $userId = $this->organizerUser()->id;
            NotificationPreference::on('pgsql_admin')
                ->where('user_id', $userId)
                ->whereNull('organization_id')
                ->whereNull('event_id')
                ->delete();
        }

        parent::tearDown();
    }

    public function test_preferences_catalogue_round_trips(): void
    {
        $this->actingAsOrganizer();

        $this->getJson('/api/v1/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.0.category', 'meetings');

        $this->putJson('/api/v1/notification-preferences', [
            'prefs' => [
                ['category' => 'meeting_status', 'email' => false, 'in_app' => false],
                ['category' => 'messages', 'email' => true, 'in_app' => true],
            ],
        ])->assertOk()
            ->assertJsonPath('data.7.category', 'meeting_status')
            ->assertJsonPath('data.7.in_app', false);
    }

    public function test_in_app_is_skipped_when_meeting_status_is_off(): void
    {
        Mail::fake();
        $user = $this->actingAsOrganizer();
        $event = $this->createEvent();
        app(TenantContext::class)->set($this->tenantOrg->id);
        $eventId = \App\Models\Event::where('uuid', $event['id'])->value('id');

        $this->setPref($user->id, 'meeting_status', false, false);
        $this->setPref($user->id, 'meetings', false, false);

        app(NotificationService::class)->notify(
            'user',
            $user->id,
            $this->tenantOrg->id,
            $eventId,
            'meeting.requested',
            ['title' => 'New meeting request', 'body' => 'Hello'],
            ['in_app', 'email'],
        );

        $this->assertSame(0, Notification::withoutGlobalScopes()
            ->where('notifiable_type', 'user')
            ->where('notifiable_id', $user->id)
            ->where('template_key', 'meeting.requested')
            ->count());

        Mail::assertNothingSent();
    }

    public function test_in_app_delivers_when_meeting_status_is_on(): void
    {
        $user = $this->actingAsOrganizer();
        $event = $this->createEvent();
        app(TenantContext::class)->set($this->tenantOrg->id);
        $eventId = \App\Models\Event::where('uuid', $event['id'])->value('id');

        $this->setPref($user->id, 'meeting_status', false, true);

        app(NotificationService::class)->notify(
            'user',
            $user->id,
            $this->tenantOrg->id,
            $eventId,
            'meeting.requested',
            ['title' => 'New meeting request', 'body' => 'Hello'],
            ['in_app'],
        );

        $this->assertSame(1, Notification::withoutGlobalScopes()
            ->where('notifiable_type', 'user')
            ->where('notifiable_id', $user->id)
            ->where('channel', 'in_app')
            ->where('template_key', 'meeting.requested')
            ->count());
    }

    public function test_profile_views_default_off_blocks_delivery(): void
    {
        $user = $this->actingAsOrganizer();
        $event = $this->createEvent();
        app(TenantContext::class)->set($this->tenantOrg->id);
        $eventId = \App\Models\Event::where('uuid', $event['id'])->value('id');

        // Ensure no saved row so catalogue defaults apply (profile_views off).
        NotificationPreference::on('pgsql_admin')
            ->where('user_id', $user->id)
            ->where('category', 'profile_views')
            ->delete();

        app(NotificationService::class)->notify(
            'user',
            $user->id,
            $this->tenantOrg->id,
            $eventId,
            'engagement.profile_viewed',
            ['title' => 'Profile viewed', 'body' => 'Someone looked'],
            ['in_app', 'email'],
        );

        $this->assertSame(0, Notification::withoutGlobalScopes()
            ->where('notifiable_type', 'user')
            ->where('notifiable_id', $user->id)
            ->where('template_key', 'engagement.profile_viewed')
            ->count());
    }

    private function setPref(int $userId, string $category, bool $email, bool $inApp): void
    {
        NotificationPreference::on('pgsql_admin')->updateOrCreate(
            ['user_id' => $userId, 'category' => $category, 'organization_id' => null, 'event_id' => null],
            ['email' => $email, 'in_app' => $inApp, 'push' => $inApp, 'sms' => false],
        );
    }
}
