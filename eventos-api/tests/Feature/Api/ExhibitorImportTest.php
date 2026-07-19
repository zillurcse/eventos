<?php

namespace Tests\Feature\Api;

use App\Models\Exhibitor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * "Previous exhibitors": carrying an exhibitor from one of the organizer's past
 * events into the one they are building now.
 *
 * The interesting part is not the copy — it is what must *not* come across. An
 * exhibitor row is event-scoped, and several of its fields point at rows that
 * only exist in the old event (its package, its filter selections, its stall).
 */
class ExhibitorImportTest extends TestCase
{
    use DatabaseTransactions;

    private string $lastYear;

    private string $thisYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsOrganizer();
        $this->actAsPlan('enterprise'); // this suite needs several events (Free caps at 1)
        $this->lastYear = $this->createEvent(['name' => 'Expo 2025'])['id'];
        $this->thisYear = $this->createEvent(['name' => 'Expo 2026'])['id'];
    }

    public function test_candidates_offer_exhibitors_from_the_organizers_other_events(): void
    {
        $this->exhibitor($this->lastYear, 'Acme Robotics', 'acme@example.test');

        $rows = $this->getJson("/api/v1/exhibitors/importable?event={$this->thisYear}")
            ->assertOk()
            ->json('data');

        $acme = collect($rows)->firstWhere('email', 'acme@example.test');

        $this->assertNotNull($acme, 'last year’s exhibitor should be offered');
        $this->assertSame('Expo 2025', $acme['event']['name']);
        $this->assertFalse($acme['already_added']);
    }

    public function test_the_same_company_across_several_events_is_offered_once(): void
    {
        $older = $this->createEvent(['name' => 'Expo 2024'])['id'];
        $this->exhibitor($older, 'Acme Robotics', 'acme@example.test');
        $this->exhibitor($this->lastYear, 'Acme Robotics', 'ACME@example.test'); // same identity, different case

        $rows = collect($this->getJson("/api/v1/exhibitors/importable?event={$this->thisYear}")->json('data'))
            ->filter(fn ($r) => strcasecmp((string) $r['email'], 'acme@example.test') === 0);

        $this->assertCount(1, $rows, 'one row per company, not one per appearance');
        // The most recent appearance is the one worth copying.
        $this->assertSame('Expo 2025', $rows->first()['event']['name']);
    }

    public function test_import_copies_the_profile_but_not_the_old_events_package_or_stall(): void
    {
        $source = $this->exhibitor($this->lastYear, 'Acme Robotics', 'acme@example.test');

        // Curated profile from last year, including two event-bound fields.
        $this->putJson("/api/v1/exhibitors/{$source['id']}", [
            'about' => 'We build robots.',
            'website_url' => 'https://acme.test',
            'tags' => ['robotics'],
            'stall_no' => 'A1',
            'filter_selections' => ['f1' => ['Industry' => ['Robotics']]],
        ])->assertOk();

        $this->postJson('/api/v1/exhibitors/import', [
            'event' => $this->thisYear,
            'exhibitors' => [$source['id']],
        ])
            ->assertCreated()
            ->assertJsonPath('meta.imported', 1);

        $copy = collect($this->getJson("/api/v1/exhibitors?event={$this->thisYear}")->json('data'))
            ->firstWhere('email', 'acme@example.test');

        $this->assertSame('Acme Robotics', $copy['name']);
        $this->assertSame('We build robots.', $copy['about']);
        $this->assertSame(['robotics'], $copy['tags']);

        // The package belongs to last year's event, the stall to last year's hall,
        // and the filter selections to last year's filter rows.
        $this->assertNull($copy['package_id']);
        $this->assertArrayNotHasKey('stall_no', $copy);
        $this->assertArrayNotHasKey('filter_selections', $copy);
    }

    public function test_import_re_maps_the_package_when_the_new_event_has_one_by_the_same_name(): void
    {
        $goldLastYear = $this->package($this->lastYear, 'Gold');
        $goldThisYear = $this->package($this->thisYear, 'Gold');

        $source = $this->exhibitor($this->lastYear, 'Acme Robotics', 'acme@example.test', $goldLastYear);

        $this->postJson('/api/v1/exhibitors/import', [
            'event' => $this->thisYear,
            'exhibitors' => [$source['id']],
        ])->assertCreated();

        $copy = collect($this->getJson("/api/v1/exhibitors?event={$this->thisYear}")->json('data'))
            ->firstWhere('email', 'acme@example.test');

        // Same tier, but this event's row for it — never the other event's id.
        $this->assertSame($goldThisYear, $copy['package_id']);
        $this->assertNotSame($goldLastYear, $copy['package_id']);
    }

    public function test_import_can_carry_the_team_and_the_catalogue(): void
    {
        $source = $this->exhibitor($this->lastYear, 'Acme Robotics', 'acme@example.test');

        $this->postJson("/api/v1/exhibitors/{$source['id']}/members", [
            'email' => 'engineer@acme.test',
            'first_name' => 'Rita',
            'role' => 'staff',
        ])->assertCreated();

        $this->postJson("/api/v1/exhibitors/{$source['id']}/products", [
            'name' => 'Robot Arm',
            'price_cents' => 500000,
        ])->assertCreated();

        $this->postJson('/api/v1/exhibitors/import', [
            'event' => $this->thisYear,
            'exhibitors' => [$source['id']],
            'include' => ['members' => true, 'products' => true],
        ])->assertCreated();

        $copyUuid = collect($this->getJson("/api/v1/exhibitors?event={$this->thisYear}")->json('data'))
            ->firstWhere('email', 'acme@example.test')['id'];

        $full = $this->getJson("/api/v1/exhibitors/{$copyUuid}")->assertOk()->json('data');

        $this->assertSame('Robot Arm', $full['products'][0]['name']);
        $this->assertContains(
            'engineer@acme.test',
            collect($full['members'])->pluck('contact.email')->all(),
        );

        // The member's participation belongs to the old event — it must not ride along.
        $copy = Exhibitor::where('uuid', $copyUuid)->firstOrFail();
        $this->assertNull($copy->members()->first()->participation_id);
    }

    public function test_importing_an_exhibitor_that_is_already_here_skips_instead_of_duplicating(): void
    {
        $source = $this->exhibitor($this->lastYear, 'Acme Robotics', 'acme@example.test');

        $body = ['event' => $this->thisYear, 'exhibitors' => [$source['id']]];

        $this->postJson('/api/v1/exhibitors/import', $body)->assertCreated();

        // Same click again — the classic double-submit.
        $this->postJson('/api/v1/exhibitors/import', $body)
            ->assertCreated()
            ->assertJsonPath('meta.imported', 0)
            ->assertJsonPath('meta.skipped.0.reason', 'Already in this event');

        $here = collect($this->getJson("/api/v1/exhibitors?event={$this->thisYear}")->json('data'))
            ->where('email', 'acme@example.test');

        $this->assertCount(1, $here, 'the exhibitor must not be duplicated');

        // …and they are now flagged in the picker rather than silently offered again.
        $offered = collect($this->getJson("/api/v1/exhibitors/importable?event={$this->thisYear}")->json('data'))
            ->firstWhere('email', 'acme@example.test');
        $this->assertTrue($offered['already_added']);
    }

    public function test_an_exhibitor_of_the_target_event_is_never_a_candidate_for_itself(): void
    {
        $this->exhibitor($this->thisYear, 'Already Here', 'here@example.test');

        $rows = collect($this->getJson("/api/v1/exhibitors/importable?event={$this->thisYear}")->json('data'));

        $this->assertNull($rows->firstWhere('email', 'here@example.test'));
    }

    // ── Fixtures ────────────────────────────────────────────────────────────
    private function package(string $eventUuid, string $name): int
    {
        return $this->postJson('/api/v1/exhibitor-packages', [
            'event' => $eventUuid,
            'name' => $name,
            'price_cents' => 100000,
        ])->assertCreated()->json('data.id');
    }

    private function exhibitor(string $eventUuid, string $name, string $email, ?int $packageId = null): array
    {
        return $this->postJson('/api/v1/exhibitors', [
            'event' => $eventUuid,
            'name' => $name,
            'email' => $email,
            'package_id' => $packageId ?? $this->package($eventUuid, 'Standard '.uniqid()),
        ])->assertCreated()->json('data');
    }
}
