<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * "Previous speakers": re-seating someone who spoke at one of the organizer's
 * earlier events.
 *
 * A speaker is a participation on a contact, so the same person at two events is
 * the same contact_id — the copy is easy. The tests are about what must *not*
 * come across: last year's talk, last year's category, last year's home-page
 * billing.
 */
class SpeakerImportTest extends TestCase
{
    use DatabaseTransactions;

    private string $lastYear;

    private string $thisYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsOrganizer();
        $this->lastYear = $this->createEvent(['name' => 'Summit 2025'])['id'];
        $this->thisYear = $this->createEvent(['name' => 'Summit 2026'])['id'];
    }

    public function test_candidates_offer_speakers_from_the_organizers_other_events(): void
    {
        $this->speaker($this->lastYear, 'Grace Hopper', 'grace@example.test', ['company' => 'Navy']);

        $rows = $this->getJson("/api/v1/events/{$this->thisYear}/speakers/importable")
            ->assertOk()
            ->json('data');

        $grace = collect($rows)->firstWhere('email', 'grace@example.test');

        $this->assertNotNull($grace);
        $this->assertSame('Summit 2025', $grace['event']['name']);
        $this->assertSame('Navy', $grace['company']);
        $this->assertFalse($grace['already_added']);
    }

    public function test_a_speaker_of_several_past_events_is_offered_once(): void
    {
        $older = $this->createEvent(['name' => 'Summit 2024'])['id'];
        $this->speaker($older, 'Grace Hopper', 'grace@example.test');
        $this->speaker($this->lastYear, 'Grace Hopper', 'grace@example.test');

        $rows = collect($this->getJson("/api/v1/events/{$this->thisYear}/speakers/importable")->json('data'))
            ->where('email', 'grace@example.test');

        $this->assertCount(1, $rows, 'one row per person, not one per appearance');
        $this->assertSame('Summit 2025', $rows->first()['event']['name'], 'the most recent appearance');
    }

    public function test_import_copies_the_person_but_not_last_years_talk_or_billing(): void
    {
        $this->speaker($this->lastYear, 'Grace Hopper', 'grace@example.test', [
            'designation' => 'Rear Admiral',
            'company' => 'Navy',
            'bio' => 'Invented the compiler.',
            'linkedin' => 'https://linkedin.test/grace',
            'tags' => ['compilers'],
            'presentation_title' => 'On Nanoseconds',
            'is_featured' => true,
        ]);

        $this->import([$this->candidateId('grace@example.test')])
            ->assertCreated()
            ->assertJsonPath('meta.imported', 1);

        $copy = $this->speakerIn($this->thisYear, 'grace@example.test');

        // The person travels…
        $this->assertSame('Rear Admiral', $copy['designation']);
        $this->assertSame('Navy', $copy['company']);
        $this->assertSame('Invented the compiler.', $copy['bio']);
        $this->assertSame(['compilers'], $copy['tags']);

        // …their appearance at the old event does not.
        $this->assertSame('', $copy['presentation_title'], 'they are giving a new talk');
        $this->assertFalse($copy['is_featured'], 'featuring is an editorial call per event');
    }

    public function test_the_presentation_can_be_carried_over_when_asked_for(): void
    {
        $this->speaker($this->lastYear, 'Grace Hopper', 'grace@example.test', [
            'presentation_title' => 'On Nanoseconds',
        ]);

        $this->import([$this->candidateId('grace@example.test')], ['presentation' => true])
            ->assertCreated();

        $this->assertSame('On Nanoseconds', $this->speakerIn($this->thisYear, 'grace@example.test')['presentation_title']);
    }

    public function test_the_category_is_re_mapped_to_this_events_category_of_the_same_name(): void
    {
        $this->category($this->lastYear, 'Keynote');
        $this->speaker($this->lastYear, 'Grace Hopper', 'grace@example.test', ['category' => 'Keynote']);
        $this->speaker($this->lastYear, 'Alan Turing', 'alan@example.test', ['category' => 'Keynote']);

        // This event knows "Keynote"; it does not know last year's other tracks.
        $this->category($this->thisYear, 'Keynote');

        $this->import([
            $this->candidateId('grace@example.test'),
            $this->candidateId('alan@example.test'),
        ])->assertCreated()->assertJsonPath('meta.imported', 2);

        $this->assertSame('Keynote', $this->speakerIn($this->thisYear, 'grace@example.test')['category']);
    }

    public function test_a_category_this_event_does_not_have_is_dropped_rather_than_invented(): void
    {
        $this->category($this->lastYear, 'Fireside');
        $this->speaker($this->lastYear, 'Grace Hopper', 'grace@example.test', ['category' => 'Fireside']);

        $this->import([$this->candidateId('grace@example.test')])->assertCreated();

        // Last year's category is a row in last year's event.meta — not this one's.
        $this->assertSame('', $this->speakerIn($this->thisYear, 'grace@example.test')['category']);
    }

    public function test_importing_someone_who_already_speaks_here_skips_instead_of_duplicating(): void
    {
        $this->speaker($this->lastYear, 'Grace Hopper', 'grace@example.test');
        $id = $this->candidateId('grace@example.test');

        $this->import([$id])->assertCreated()->assertJsonPath('meta.imported', 1);

        $this->import([$id])
            ->assertCreated()
            ->assertJsonPath('meta.imported', 0)
            ->assertJsonPath('meta.skipped.0.reason', 'Already speaking at this event');

        $here = collect($this->getJson("/api/v1/events/{$this->thisYear}/speakers")->json('data'))
            ->where('email', 'grace@example.test');

        $this->assertCount(1, $here);

        $offered = collect($this->getJson("/api/v1/events/{$this->thisYear}/speakers/importable")->json('data'))
            ->firstWhere('email', 'grace@example.test');
        $this->assertTrue($offered['already_added']);
    }

    public function test_an_imported_speaker_keeps_the_login_they_already_had(): void
    {
        $this->speaker($this->lastYear, 'Grace Hopper', 'grace@example.test');

        $this->import([$this->candidateId('grace@example.test')])->assertCreated();

        // The contact — and the account hanging off it — is reused, so they can
        // sign in to the new event site immediately.
        $this->assertTrue($this->speakerIn($this->thisYear, 'grace@example.test')['has_login']);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────
    private function speaker(string $eventUuid, string $name, string $email, array $profile = []): array
    {
        return $this->postJson("/api/v1/events/{$eventUuid}/speakers", array_merge([
            'name' => $name,
            'email' => $email,
        ], $profile))->assertCreated()->json('data');
    }

    private function category(string $eventUuid, string $name): void
    {
        $this->postJson("/api/v1/events/{$eventUuid}/speaker-categories", ['name' => $name])->assertCreated();
    }

    /** The importable-candidate uuid for a person (their participation at the past event). */
    private function candidateId(string $email): string
    {
        return collect($this->getJson("/api/v1/events/{$this->thisYear}/speakers/importable")->json('data'))
            ->firstWhere('email', $email)['id'];
    }

    private function import(array $ids, array $include = [])
    {
        return $this->postJson("/api/v1/events/{$this->thisYear}/speakers/import", [
            'speakers' => $ids,
            'include' => $include,
        ]);
    }

    private function speakerIn(string $eventUuid, string $email): array
    {
        return collect($this->getJson("/api/v1/events/{$eventUuid}/speakers")->json('data'))
            ->firstWhere('email', $email);
    }
}
