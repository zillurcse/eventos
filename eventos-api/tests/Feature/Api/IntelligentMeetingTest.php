<?php

namespace Tests\Feature\Api;

use App\Models\Contact;
use App\Models\Event;
use App\Models\EventSetting;
use App\Models\Meeting;
use App\Models\Participation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Communication → Meetings › Intelligent Meeting.
 */
class IntelligentMeetingTest extends TestCase
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

        Event::where('id', $this->eventId)->update(['format' => 'venue']);

        EventSetting::firstOrCreate(['event_id' => $this->eventId])->update([
            'meeting' => [
                'intelligent' => true,
                'slot_duration' => 15,
                'locations' => ['Hall 4'],
            ],
            'lounge' => [
                'enabled' => false,
                'attendee_tables_enabled' => true,
                'attendee_tables' => [
                    ['id' => 't1', 'name' => 'Table Alpha', 'capacity' => 4, 'design' => 'round'],
                    ['id' => 't2', 'name' => 'Table Beta', 'capacity' => 4, 'design' => 'round'],
                ],
            ],
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

    public function test_capabilities_expose_intelligent_flag(): void
    {
        [$aliceUser] = $this->attendeePair('alice');
        Sanctum::actingAs($aliceUser);

        $this->getJson("/api/v1/events/{$this->eventUuid}/meetings/capabilities")
            ->assertOk()
            ->assertJsonPath('data.intelligent', true)
            ->assertJsonPath('data.locations.0', 'Hall 4');
    }

    public function test_lounge_auto_generates_slots_when_intelligent(): void
    {
        [$aliceUser, $alice] = $this->attendeePair('alice');
        [, $bob] = $this->attendeePair('bob');
        Sanctum::actingAs($aliceUser);

        $res = $this->getJson("/api/v1/events/{$this->eventUuid}/lounge?with={$bob->uuid}")
            ->assertOk();

        $this->assertTrue($res->json('data.intelligent'));
        $this->assertTrue($res->json('data.enabled'));
        $this->assertFalse($res->json('data.location_required'));

        $dates = $res->json('data.dates');
        $this->assertNotEmpty($dates);
        $firstDate = $dates[0];
        $slots = $res->json("data.slots.{$firstDate}");
        $this->assertNotEmpty($slots);
        $this->assertStringContainsString('-', $slots[0]);
    }

    public function test_accepting_meeting_allocates_table_and_location(): void
    {
        [$aliceUser, $alice] = $this->attendeePair('alice');
        [$bobUser, $bob] = $this->attendeePair('bob');

        Sanctum::actingAs($aliceUser);

        $event = Event::findOrFail($this->eventId);
        $date = $event->starts_at?->format('Y-m-d') ?? now()->format('Y-m-d');

        $create = $this->postJson("/api/v1/events/{$this->eventUuid}/meetings", [
            'invitees' => [$bob->uuid],
            'title' => 'Coffee chat',
            'date' => $date,
            'slot' => '10:00-10:15',
        ])->assertCreated();

        $meetingUuid = $create->json('data.id');

        Sanctum::actingAs($bobUser);

        $this->patchJson("/api/v1/events/{$this->eventUuid}/meetings/{$meetingUuid}", [
            'action' => 'accept',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.allocated_table.name', 'Table Alpha')
            ->assertJsonPath('data.location', 'Hall 4 · Table Alpha');
    }

    public function test_area_map_lists_tables_with_bookings(): void
    {
        [$aliceUser, $alice] = $this->attendeePair('alice');
        [$bobUser, $bob] = $this->attendeePair('bob');

        Sanctum::actingAs($aliceUser);

        $event = Event::findOrFail($this->eventId);
        $date = $event->starts_at?->format('Y-m-d') ?? now()->format('Y-m-d');

        $meeting = Meeting::create([
            'event_id' => $this->eventId,
            'organization_id' => $this->orgId,
            'organizer_participation_id' => $alice->id,
            'title' => 'Booked',
            'type' => 'one_on_one',
            'status' => 'confirmed',
            'location' => 'Hall 4 · Table Alpha',
            'meta' => [
                'lounge_date' => $date,
                'lounge_slot' => '11:00-11:15',
                'allocated_table_id' => 'att_t1',
                'allocated_table_name' => 'Table Alpha',
            ],
        ]);
        $meeting->participants()->attach([
            $alice->id => ['role' => 'host', 'rsvp' => 'accepted'],
            $bob->id => ['role' => 'guest', 'rsvp' => 'accepted'],
        ]);

        Sanctum::actingAs($bobUser);

        $this->getJson("/api/v1/events/{$this->eventUuid}/meetings/area")
            ->assertOk()
            ->assertJsonPath('data.tables.0.name', 'Table Alpha')
            ->assertJsonPath('data.tables.0.bookings.0.slot', '11:00-11:15');
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
}
