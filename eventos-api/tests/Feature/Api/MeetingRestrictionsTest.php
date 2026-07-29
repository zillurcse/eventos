<?php

namespace Tests\Feature\Api;

use App\Models\Contact;
use App\Models\Event;
use App\Models\EventSetting;
use App\Models\Exhibitor;
use App\Models\Meeting;
use App\Models\Participation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Communication → Meetings → Meeting Restriction caps (requests + confirmed).
 *
 * No DatabaseTransactions: participant routes resolve on the admin connection.
 */
class MeetingRestrictionsTest extends TestCase
{
    private string $eventUuid;

    private int $eventId;

    private int $orgId;

    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $contactIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsOrganizer();
        $this->eventUuid = $this->createEvent()['id'];

        $event = Event::where('uuid', $this->eventUuid)->firstOrFail();
        $this->eventId = $event->id;
        $this->orgId = $event->organization_id;

        EventSetting::firstOrCreate(['event_id' => $this->eventId])->update([
            'meeting' => [
                'restrictions' => [
                    'attendee' => ['requests' => 1, 'confirmed' => 1],
                ],
            ],
        ]);
    }

    /** @var list<int> */
    private array $exhibitorIds = [];

    protected function tearDown(): void
    {
        $admin = DB::connection(self::ADMIN_CONN);
        if ($this->exhibitorIds) {
            $admin->table('exhibitors')->whereIn('id', $this->exhibitorIds)->delete();
        }
        $admin->table('events')->where('uuid', $this->eventUuid)->delete();
        if ($this->contactIds) {
            $admin->table('contacts')->whereIn('id', $this->contactIds)->delete();
        }
        if ($this->userIds) {
            $admin->table('users')->whereIn('id', $this->userIds)->delete();
        }

        parent::tearDown();
    }

    public function test_meetings_tab_disabled_blocks_exhibitor_meeting_request(): void
    {
        EventSetting::where('event_id', $this->eventId)->update([
            'navigation' => [
                'web_app_tabs' => [
                    'items' => [
                        ['key' => 'reception', 'label' => 'Reception', 'enabled' => true],
                        ['key' => 'meetings', 'label' => 'Meetings', 'enabled' => false],
                    ],
                ],
            ],
        ]);

        [$aliceUser] = $this->attendeePair('alice');
        $exhibitor = $this->exhibitorCompany();

        Sanctum::actingAs($aliceUser);

        $this->postJson("/api/v1/events/{$this->eventUuid}/exhibitors/{$exhibitor->uuid}/meeting-requests", [
            'subject' => 'Booth meet',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Meetings are not enabled for this event.');
    }

    public function test_permission_matrix_blocks_exhibitor_meeting_request(): void
    {
        EventSetting::where('event_id', $this->eventId)->update([
            'meeting' => [
                'permissions' => [
                    'attendee' => [
                        'attendee' => true,
                        'speaker' => true,
                        'exhibitor' => false,
                        'sponsor' => true,
                    ],
                ],
            ],
        ]);

        [$aliceUser, $alice] = $this->attendeePair('alice');
        $exhibitor = $this->exhibitorCompany();

        Sanctum::actingAs($aliceUser);

        $this->postJson("/api/v1/events/{$this->eventUuid}/exhibitors/{$exhibitor->uuid}/meeting-requests", [
            'subject' => 'Booth meet',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'The organizer has not enabled meetings with this role.');
    }

    public function test_outgoing_request_cap_blocks_a_second_meeting_request(): void
    {
        [$aliceUser, $alice] = $this->attendeePair('alice');
        [, $bob] = $this->attendeePair('bob');

        Sanctum::actingAs($aliceUser);

        $this->postJson("/api/v1/events/{$this->eventUuid}/meetings", [
            'invitees' => [$bob->uuid],
            'title' => 'First',
        ])->assertCreated();

        $this->postJson("/api/v1/events/{$this->eventUuid}/meetings", [
            'invitees' => [$bob->uuid],
            'title' => 'Second',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have reached the maximum number of meeting requests.');
    }

    public function test_confirmed_cap_blocks_accepting_a_second_meeting(): void
    {
        [$aliceUser, $alice] = $this->attendeePair('alice');
        [$_, $bob] = $this->attendeePair('bob');
        [$_, $carol] = $this->attendeePair('carol');

        // Carol → Bob (pending), Alice → Bob (pending). Bob may only confirm one.
        Meeting::create([
            'event_id' => $this->eventId,
            'organization_id' => $this->orgId,
            'organizer_participation_id' => $carol->id,
            'title' => 'From Carol',
            'type' => 'one_on_one',
            'status' => 'requested',
        ])->participants()->attach([
            $carol->id => ['role' => 'host', 'rsvp' => 'accepted'],
            $bob->id => ['role' => 'guest', 'rsvp' => 'pending'],
        ]);

        $aliceMeeting = Meeting::create([
            'event_id' => $this->eventId,
            'organization_id' => $this->orgId,
            'organizer_participation_id' => $alice->id,
            'title' => 'From Alice',
            'type' => 'one_on_one',
            'status' => 'requested',
        ]);
        $aliceMeeting->participants()->attach([
            $alice->id => ['role' => 'host', 'rsvp' => 'accepted'],
            $bob->id => ['role' => 'guest', 'rsvp' => 'pending'],
        ]);

        Sanctum::actingAs($this->userFor($bob));

        $this->patchJson("/api/v1/events/{$this->eventUuid}/meetings/{$aliceMeeting->uuid}", [
            'action' => 'accept',
        ])->assertOk();

        $carolMeeting = Meeting::where('organizer_participation_id', $carol->id)->firstOrFail();

        $this->patchJson("/api/v1/events/{$this->eventUuid}/meetings/{$carolMeeting->uuid}", [
            'action' => 'accept',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have reached the maximum number of confirmed meetings.');
    }

    private function exhibitorCompany(): Exhibitor
    {
        $exhibitor = Exhibitor::query()->withoutGlobalScope('organization')->forceCreate([
            'event_id' => $this->eventId,
            'organization_id' => $this->orgId,
            'type' => 'exhibitor',
            'name' => 'Test Booth',
        ]);
        $exhibitor->forceFill(['status' => 'active'])->save();
        $this->exhibitorIds[] = $exhibitor->id;

        return $exhibitor;
    }

    /** @return array{0: User, 1: Participation} */
    private function attendeePair(string $prefix): array
    {
        $user = (new User)->setConnection(self::ADMIN_CONN);
        $user->forceFill([
            'name' => ucfirst($prefix),
            'email' => $prefix.'-'.uniqid().'@example.test',
            'password' => 'password',
            'email_verified_at' => now(),
        ])->save();

        $contact = Contact::create([
            'organization_id' => $this->orgId,
            'user_id' => $user->id,
            'email' => $user->email,
            'first_name' => ucfirst($prefix),
            'last_name' => 'Test',
        ]);

        $participation = Participation::query()->withoutGlobalScope('organization')->forceCreate([
            'event_id' => $this->eventId,
            'organization_id' => $this->orgId,
            'contact_id' => $contact->id,
            'role' => 'attendee',
            'status' => 'registered',
        ]);

        $this->userIds[] = $user->id;
        $this->contactIds[] = $contact->id;

        return [$user, $participation];
    }

    private function userFor(Participation $participation): User
    {
        return User::on(self::ADMIN_CONN)->where('id', $participation->contact->user_id)->firstOrFail();
    }
}
