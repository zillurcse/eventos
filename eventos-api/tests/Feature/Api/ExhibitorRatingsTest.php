<?php

namespace Tests\Feature\Api;

use App\Models\Contact;
use App\Models\Exhibitor;
use App\Models\Participation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Exhibitor ratings are participant-scoped, so these tests commit fixtures and
 * clean them up manually — the participant middleware resolves on the admin
 * connection and cannot see uncommitted tenant rows inside a transaction.
 */
class ExhibitorRatingsTest extends TestCase
{
    private string $eventUuid;

    private string $exhibitorUuid;

    private Exhibitor $exhibitor;

    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $contactIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsOrganizer();
        $this->eventUuid = $this->createEvent()['id'];

        $this->exhibitorUuid = $this->postJson('/api/v1/exhibitors', [
            'event' => $this->eventUuid,
            'type' => 'exhibitor',
            'name' => 'Ratings Test Exhibitor',
            'email' => 'ratings-test-'.uniqid().'@example.test',
            'package_id' => $this->createPackage(),
            'contact' => [
                'full_name' => 'Ratings Admin',
                'position' => 'Manager',
                'company_name' => 'Ratings Test Exhibitor',
                'email' => 'contact-'.uniqid().'@example.test',
                'phone' => '123456789',
                'phone_code' => '+880',
            ],
            'rating' => true,
        ])->assertCreated()->json('data.id');

        $this->exhibitor = Exhibitor::where('uuid', $this->exhibitorUuid)->firstOrFail();
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

    public function test_attendees_can_rate_an_exhibitor_and_organizer_can_view_the_rollup(): void
    {
        [$firstUser] = $this->attendee('Ada', 'Lovelace');
        [$secondUser] = $this->attendee('Grace', 'Hopper');

        Sanctum::actingAs($firstUser);
        $this->postJson("/api/v1/events/{$this->eventUuid}/exhibitors/{$this->exhibitorUuid}/rating", [
            'score' => 4,
        ])
            ->assertOk()
            ->assertJsonPath('data.score', 4)
            ->assertJsonPath('data.ratings_count', 1)
            ->assertJsonPath('data.average_score', 4);

        Sanctum::actingAs($secondUser);
        $this->postJson("/api/v1/events/{$this->eventUuid}/exhibitors/{$this->exhibitorUuid}/rating", [
            'score' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.score', 2)
            ->assertJsonPath('data.ratings_count', 2)
            ->assertJsonPath('data.average_score', 3);

        Sanctum::actingAs($firstUser);
        $this->postJson("/api/v1/events/{$this->eventUuid}/exhibitors/{$this->exhibitorUuid}/rating", [
            'score' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.score', 5)
            ->assertJsonPath('data.ratings_count', 2)
            ->assertJsonPath('data.average_score', 3.5);

        $this->actingAsOrganizer();
        $response = $this->getJson("/api/v1/exhibitors/{$this->exhibitorUuid}/ratings")
            ->assertOk()
            ->assertJsonPath('data.exhibitor.id', $this->exhibitorUuid)
            ->assertJsonPath('data.summary.ratings_count', 2)
            ->assertJsonPath('data.summary.average_score', 3.5);

        $distribution = collect($response->json('data.summary.distribution'))->pluck('count', 'score');
        $this->assertSame(0, (int) $distribution[1]);
        $this->assertSame(1, (int) $distribution[2]);
        $this->assertSame(0, (int) $distribution[3]);
        $this->assertSame(0, (int) $distribution[4]);
        $this->assertSame(1, (int) $distribution[5]);

        $ratings = collect($response->json('data.ratings'));
        $this->assertCount(2, $ratings);
        $this->assertTrue($ratings->contains(fn (array $row) => ($row['participation']['email'] ?? null) === $firstUser->email && (int) $row['score'] === 5));
        $this->assertTrue($ratings->contains(fn (array $row) => ($row['participation']['email'] ?? null) === $secondUser->email && (int) $row['score'] === 2));
    }

    public function test_rating_an_exhibitor_is_blocked_when_the_exhibitor_disables_ratings(): void
    {
        $this->actingAsOrganizer();
        $this->patchJson("/api/v1/exhibitors/{$this->exhibitorUuid}", ['rating' => false])->assertOk();

        [$user] = $this->attendee('Blocked', 'Attendee');
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/events/{$this->eventUuid}/exhibitors/{$this->exhibitorUuid}/rating", [
            'score' => 3,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['score']);
    }

    private function attendee(string $first, string $last): array
    {
        $user = (new User)->setConnection(self::ADMIN_CONN);
        $user->forceFill([
            'name' => trim("{$first} {$last}"),
            'email' => strtolower($first).'-'.uniqid().'@example.test',
            'password' => 'password',
            'email_verified_at' => now(),
        ])->save();

        $contact = Contact::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'first_name' => $first,
            'last_name' => $last,
        ]);

        $participation = Participation::create([
            'event_id' => $this->exhibitor->event_id,
            'contact_id' => $contact->id,
            'role' => 'attendee',
            'status' => 'confirmed',
        ]);

        $this->userIds[] = $user->id;
        $this->contactIds[] = $contact->id;

        return [$user, $participation];
    }
}
