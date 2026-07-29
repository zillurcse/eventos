<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Programme building blocks: venues, rooms, tracks, sessions and speakers. */
class ProgramTest extends TestCase
{
    use DatabaseTransactions;

    public function test_venue_room_track_session_and_speaker_flow(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();
        $eventUuid = $event['id'];

        // Venue.
        $venueId = $this->postJson('/api/v1/venues', [
            'name' => 'Main Hall',
            'city' => 'Lisbon',
            'country' => 'Portugal',
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/venues')->assertOk()->assertJsonStructure(['data']);

        // Room (belongs to the venue).
        $roomId = $this->postJson('/api/v1/rooms', [
            'venue_id' => $venueId,
            'name' => 'Room A',
            'capacity' => 120,
        ])->assertCreated()->json('data.id');

        // Track.
        $trackId = $this->postJson('/api/v1/tracks', [
            'event' => $eventUuid,
            'name' => 'Keynotes',
            'color' => '#ff0000',
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/v1/tracks?event={$eventUuid}")->assertOk();

        // Session referencing the track + room.
        $sessionUuid = $this->postJson('/api/v1/sessions', [
            'event' => $eventUuid,
            'title' => 'Opening Keynote',
            'track_id' => $trackId,
            'room_id' => $roomId,
            'starts_at' => now()->addWeek()->toIso8601String(),
            'ends_at' => now()->addWeek()->addHour()->toIso8601String(),
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/v1/sessions/{$sessionUuid}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Opening Keynote');

        // Add a speaker (upserts contact → participation → pivot).
        $this->postJson("/api/v1/sessions/{$sessionUuid}/speakers", [
            'email' => 'speaker-'.uniqid().'@example.test',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ])->assertOk()->assertJsonStructure(['data' => ['id', 'title']]);

        // Update + delete the session.
        $this->patchJson("/api/v1/sessions/{$sessionUuid}", ['title' => 'Opening Keynote (Updated)'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Opening Keynote (Updated)');

        $this->deleteJson("/api/v1/sessions/{$sessionUuid}")->assertNoContent();
    }

    public function test_creating_a_session_validates_required_fields(): void
    {
        $this->actingAsOrganizer();

        $this->postJson('/api/v1/sessions', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event', 'title']);
    }

    public function test_cannot_schedule_two_sessions_on_the_same_track_at_overlapping_times(): void
    {
        $this->actingAsOrganizer();
        $eventUuid = $this->createEvent()['id'];

        $trackId = $this->postJson('/api/v1/tracks', [
            'event' => $eventUuid,
            'name' => 'Main Stage',
            'color' => '#3366ff',
        ])->assertCreated()->json('data.id');

        $otherTrackId = $this->postJson('/api/v1/tracks', [
            'event' => $eventUuid,
            'name' => 'Breakout',
            'color' => '#22aa66',
        ])->assertCreated()->json('data.id');

        $start = now()->addWeek()->startOfHour();
        $end = $start->copy()->addHour();

        $this->postJson('/api/v1/sessions', [
            'event' => $eventUuid,
            'title' => 'First talk',
            'track_id' => $trackId,
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
        ])->assertCreated();

        // Same track + overlapping window → rejected.
        $this->postJson('/api/v1/sessions', [
            'event' => $eventUuid,
            'title' => 'Conflicting talk',
            'track_id' => $trackId,
            'starts_at' => $start->copy()->addMinutes(30)->toIso8601String(),
            'ends_at' => $end->copy()->addMinutes(30)->toIso8601String(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['track_id']);

        // Same time on a different track is fine.
        $this->postJson('/api/v1/sessions', [
            'event' => $eventUuid,
            'title' => 'Parallel talk',
            'track_id' => $otherTrackId,
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
        ])->assertCreated();

        // Adjacent (touching endpoint only) on the same track is fine.
        $this->postJson('/api/v1/sessions', [
            'event' => $eventUuid,
            'title' => 'Next slot',
            'track_id' => $trackId,
            'starts_at' => $end->toIso8601String(),
            'ends_at' => $end->copy()->addHour()->toIso8601String(),
        ])->assertCreated();
    }

    public function test_editing_a_session_cannot_move_onto_an_occupied_track_slot(): void
    {
        $this->actingAsOrganizer();
        $eventUuid = $this->createEvent()['id'];

        $trackId = $this->postJson('/api/v1/tracks', [
            'event' => $eventUuid,
            'name' => 'Main Stage',
            'color' => '#3366ff',
        ])->assertCreated()->json('data.id');

        $start = now()->addWeek()->startOfHour();
        $end = $start->copy()->addHour();

        $this->postJson('/api/v1/sessions', [
            'event' => $eventUuid,
            'title' => 'Occupied slot',
            'track_id' => $trackId,
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
        ])->assertCreated();

        $movableUuid = $this->postJson('/api/v1/sessions', [
            'event' => $eventUuid,
            'title' => 'Movable talk',
            'track_id' => $trackId,
            'starts_at' => $end->toIso8601String(),
            'ends_at' => $end->copy()->addHour()->toIso8601String(),
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/sessions/{$movableUuid}", [
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['track_id']);

        // Saving itself without changing the schedule must still succeed.
        $this->putJson("/api/v1/sessions/{$movableUuid}", [
            'title' => 'Movable talk (renamed)',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Movable talk (renamed)');
    }

    public function test_creating_a_venue_requires_a_name(): void
    {
        $this->actingAsOrganizer();

        $this->postJson('/api/v1/venues', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
