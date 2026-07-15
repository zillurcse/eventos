<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\EventSetting;
use App\Models\User;
use App\Services\Auth\EventAccess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Access authentication (Settings › Access authentication): OTP sign-in, the
 * event-admin list, and the channel/onboarding config the login page reads.
 *
 * Like the other participant-facing tests, no DatabaseTransactions: the public
 * sign-in paths resolve the event on the admin (BYPASSRLS) connection, which
 * cannot see rows still uncommitted on the tenant connection. Fixtures are
 * committed and torn down explicitly.
 */
class EventAuthChannelsTest extends TestCase
{
    private string $eventUuid;

    private string $subdomain;

    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $contactIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsOrganizer();
        $this->subdomain = 'auth-test-'.substr(uniqid(), -6);
        $this->eventUuid = $this->createEvent(['name' => 'Auth Test Event'])['id'];

        // Publish + give it a subdomain so the public sign-in paths resolve it.
        $event = Event::where('uuid', $this->eventUuid)->firstOrFail();
        $event->update(['status' => 'published']);

        $setting = EventSetting::firstOrCreate(['event_id' => $event->id]);
        $setting->update([
            'domain' => ['subdomain' => $this->subdomain],
            'login' => ['methods' => ['signup' => true, 'otp' => true], 'onboarding' => true],
        ]);
    }

    protected function tearDown(): void
    {
        $admin = DB::connection(self::ADMIN_CONN);
        $admin->table('events')->where('uuid', $this->eventUuid)->delete();
        if ($this->contactIds) {
            $admin->table('contacts')->whereIn('id', $this->contactIds)->delete();
        }
        if ($this->userIds) {
            $admin->table('users')->whereIn('id', $this->userIds)->delete();
        }

        parent::tearDown();
    }

    // ── Public site payload ──────────────────────────────────────────────────
    public function test_public_site_reports_enabled_channels_and_onboarding(): void
    {
        $login = $this->getJson('/api/v1/public/site', $this->subHeader())
            ->assertOk()
            ->json('data.login');

        $this->assertTrue($login['channels']['signup']);
        $this->assertTrue($login['channels']['otp']);
        $this->assertTrue($login['onboarding']);

        // Social is reported false with no OAuth app configured, whatever the
        // organizer ticked — the login page must never draw a dead button.
        $this->assertFalse($login['channels']['google']);
        $this->assertFalse($login['channels']['facebook']);
        $this->assertFalse($login['channels']['linkedin']);
    }

    // ── OTP ──────────────────────────────────────────────────────────────────
    public function test_otp_request_and_verify_signs_in_and_enrols_the_participant(): void
    {
        $email = 'otp-'.substr(uniqid(), -8).'@example.test';

        // Request always answers the same, whether or not the address is known.
        $this->postJson('/api/v1/public/auth/otp', ['email' => $email], $this->subHeader())
            ->assertOk()
            ->assertJsonPath('sent', true);

        // We can't read the emailed code, so pull it from the cache the way the
        // controller stored it — but it's hashed, so seed a known one instead.
        $event = Event::where('uuid', $this->eventUuid)->firstOrFail();
        Cache::put('otp:'.$event->id.':'.sha1($email), [
            'hash' => \Illuminate\Support\Facades\Hash::make('123456'),
            'attempts' => 0,
        ], 600);

        $res = $this->postJson('/api/v1/public/auth/otp/verify', [
            'email' => $email, 'code' => '123456',
        ], $this->subHeader())->assertOk();

        $this->assertNotEmpty($res->json('token'));
        $this->assertSame($email, $res->json('user.email'));

        $this->trackUser($email);

        // Signing in by OTP must make them a participant of this event.
        $participation = DB::connection(self::ADMIN_CONN)->table('participations')
            ->join('contacts', 'contacts.id', '=', 'participations.contact_id')
            ->where('participations.event_id', $event->id)
            ->where('contacts.email', $email)
            ->first();

        $this->assertNotNull($participation, 'OTP sign-in should enrol a participation');
        $this->assertSame('attendee', $participation->role);
    }

    public function test_otp_rejects_a_wrong_code_and_burns_a_correct_one_after_use(): void
    {
        $email = 'otp2-'.substr(uniqid(), -8).'@example.test';
        $event = Event::where('uuid', $this->eventUuid)->firstOrFail();
        $key = 'otp:'.$event->id.':'.sha1($email);

        Cache::put($key, ['hash' => \Illuminate\Support\Facades\Hash::make('654321'), 'attempts' => 0], 600);

        $this->postJson('/api/v1/public/auth/otp/verify', ['email' => $email, 'code' => '000000'], $this->subHeader())
            ->assertStatus(422);

        $this->postJson('/api/v1/public/auth/otp/verify', ['email' => $email, 'code' => '654321'], $this->subHeader())
            ->assertOk();
        $this->trackUser($email);

        // Single use — the same code cannot be replayed.
        $this->postJson('/api/v1/public/auth/otp/verify', ['email' => $email, 'code' => '654321'], $this->subHeader())
            ->assertStatus(422);
    }

    public function test_otp_self_enrols_a_brand_new_email_even_when_signup_is_closed(): void
    {
        $event = Event::where('uuid', $this->eventUuid)->firstOrFail();
        EventSetting::where('event_id', $event->id)->update([
            'login' => ['methods' => ['signup' => false, 'otp' => true]],
        ]);

        $email = 'newcomer-'.substr(uniqid(), -8).'@example.test';

        Cache::put('otp:'.$event->id.':'.sha1($email), [
            'hash' => \Illuminate\Support\Facades\Hash::make('112233'),
            'attempts' => 0,
        ], 600);

        // OTP alone is enough to let a never-seen-before address in — Signup
        // being closed only retires the password-based registration form.
        $this->postJson('/api/v1/public/auth/otp/verify', [
            'email' => $email, 'code' => '112233',
        ], $this->subHeader())->assertOk();

        $this->trackUser($email);
    }

    public function test_otp_is_refused_when_the_channel_is_off(): void
    {
        $event = Event::where('uuid', $this->eventUuid)->firstOrFail();
        EventSetting::where('event_id', $event->id)->update([
            'login' => ['methods' => ['signup' => true, 'otp' => false]],
        ]);

        $this->postJson('/api/v1/public/auth/otp', ['email' => 'x@example.test'], $this->subHeader())
            ->assertStatus(403);
    }

    // ── Event admins ─────────────────────────────────────────────────────────
    public function test_adding_an_event_admin_creates_a_staff_participation(): void
    {
        $email = 'admin-'.substr(uniqid(), -8).'@example.test';

        $this->postJson("/api/v1/events/{$this->eventUuid}/admins", ['email' => $email, 'name' => 'Ada Admin'])
            ->assertCreated()
            ->assertJsonPath('data.email', $email)
            ->assertJsonPath('meta.had_login', false); // brand-new → gets a code

        $this->trackUser($email);

        $admins = $this->getJson("/api/v1/events/{$this->eventUuid}/admins")->assertOk()->json('data');
        $this->assertContains($email, collect($admins)->pluck('email')->all());

        // They are staff — which Session::isModeratedBy treats as a host.
        $event = Event::where('uuid', $this->eventUuid)->firstOrFail();
        $role = DB::connection(self::ADMIN_CONN)->table('participations')
            ->join('contacts', 'contacts.id', '=', 'participations.contact_id')
            ->where('participations.event_id', $event->id)
            ->where('contacts.email', $email)
            ->value('participations.role');

        $this->assertSame('staff', $role);
    }

    public function test_removing_an_event_admin_demotes_but_keeps_the_participation(): void
    {
        $email = 'admin2-'.substr(uniqid(), -8).'@example.test';

        $adminId = $this->postJson("/api/v1/events/{$this->eventUuid}/admins", ['email' => $email])
            ->assertCreated()->json('data.id');
        $this->trackUser($email);

        $this->deleteJson("/api/v1/events/{$this->eventUuid}/admins/{$adminId}")->assertOk();

        // Gone from the admin list…
        $admins = $this->getJson("/api/v1/events/{$this->eventUuid}/admins")->json('data');
        $this->assertNotContains($email, collect($admins)->pluck('email')->all());

        // …but still a participant of the event (now an attendee).
        $event = Event::where('uuid', $this->eventUuid)->firstOrFail();
        $role = DB::connection(self::ADMIN_CONN)->table('participations')
            ->join('contacts', 'contacts.id', '=', 'participations.contact_id')
            ->where('participations.event_id', $event->id)
            ->where('contacts.email', $email)
            ->value('participations.role');

        $this->assertSame('attendee', $role);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    private function subHeader(): array
    {
        return ['X-Event-Subdomain' => $this->subdomain];
    }

    private function trackUser(string $email): void
    {
        $admin = DB::connection(self::ADMIN_CONN);
        $userId = $admin->table('users')->where('email', $email)->value('id');
        $contactId = $admin->table('contacts')->where('email', $email)->value('id');
        if ($userId) {
            $this->userIds[] = $userId;
        }
        if ($contactId) {
            $this->contactIds[] = $contactId;
        }
    }
}
