<?php

namespace Tests\Feature\Api;

use App\Models\Contact;
use App\Models\Participation;
use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Session ratings are participant-scoped, so these tests commit fixtures and
 * clean them up manually — the participant middleware resolves on the admin
 * connection and cannot see uncommitted tenant rows inside a transaction.
 */
class SessionRatingsTest extends TestCase
{
    private string $eventUuid;

    private string $sessionUuid;

    private Session $session;

    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $contactIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsOrganizer();
        $this->eventUuid = $this->createEvent()['id'];

        $this->sessionUuid = $this->postJson('/api/v1/sessions', [
            'event' => $this->eventUuid,
            'title' => 'Ratings Test Session',
            'starts_at' => now()->addWeek()->toIso8601String(),
            'ends_at' => now()->addWeek()->addHour()->toIso8601String(),
            'is_allowed_to_rate' => true,
        ])->assertCreated()->json('data.id');

        $this->session = Session::where('uuid', $this->sessionUuid)->firstOrFail();
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

    public function test_attendees_can_rate_a_session_and_organizer_can_view_the_rollup(): void
    {
        [$firstUser] = $this->attendee('Ada', 'Lovelace');
        [$secondUser] = $this->attendee('Grace', 'Hopper');

        Sanctum::actingAs($firstUser);
        $this->postJson("/api/v1/events/{$this->eventUuid}/sessions/{$this->sessionUuid}/rating", [
            'score' => 4,
        ])
            ->assertOk()
            ->assertJsonPath('data.score', 4)
            ->assertJsonPath('data.ratings_count', 1)
            ->assertJsonPath('data.average_score', 4);

        Sanctum::actingAs($secondUser);
        $this->postJson("/api/v1/events/{$this->eventUuid}/sessions/{$this->sessionUuid}/rating", [
            'score' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.score', 2)
            ->assertJsonPath('data.ratings_count', 2)
            ->assertJsonPath('data.average_score', 3);

        // Updating a rating should not create a duplicate row.
        Sanctum::actingAs($firstUser);
        $this->postJson("/api/v1/events/{$this->eventUuid}/sessions/{$this->sessionUuid}/rating", [
            'score' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.score', 5)
            ->assertJsonPath('data.ratings_count', 2)
            ->assertJsonPath('data.average_score', 3.5);

        $this->actingAsOrganizer();
        $response = $this->getJson("/api/v1/sessions/{$this->sessionUuid}/ratings")
            ->assertOk()
            ->assertJsonPath('data.session.id', $this->sessionUuid)
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

    public function test_rating_a_session_is_blocked_when_the_session_disables_ratings(): void
    {
        $this->actingAsOrganizer();
        $this->patchJson("/api/v1/sessions/{$this->sessionUuid}", ['is_allowed_to_rate' => false])->assertOk();

        [$user] = $this->attendee('Blocked', 'Attendee');
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/events/{$this->eventUuid}/sessions/{$this->sessionUuid}/rating", [
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
            'event_id' => $this->session->event_id,
            'contact_id' => $contact->id,
            'role' => 'attendee',
            'status' => 'confirmed',
        ]);

        $this->userIds[] = $user->id;
        $this->contactIds[] = $contact->id;

        return [$user, $participation];
    }
}
