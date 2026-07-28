<?php

namespace Tests\Feature\Api;

use App\Models\Contact;
use App\Models\Gamification;
use App\Models\GamificationPointEvent;
use App\Models\Participation;
use App\Services\Gamification\GamificationScorer;
use App\Support\GamificationActions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Dynamic gamification point scoring (queued awards + leaderboard). */
class GamificationScoringTest extends TestCase
{
    use DatabaseTransactions;

    public function test_scorer_awards_configured_points_once_per_subject(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();
        [$eventId, $orgId] = $this->eventIds($event['id']);

        Gamification::query()->withoutGlobalScope('organization')->updateOrCreate(
            ['event_id' => $eventId],
            [
                'organization_id' => $orgId,
                'enabled' => true,
                'scores' => array_merge(GamificationActions::defaultScores(0), [
                    'create_feed_text_post' => 7,
                ]),
            ],
        );

        $participation = $this->makeParticipation($eventId, $orgId);

        $scorer = app(GamificationScorer::class);

        $first = $scorer->awardNow($orgId, $eventId, (int) $participation->id, 'create_feed_text_post', 'feed_post', 101);
        $second = $scorer->awardNow($orgId, $eventId, (int) $participation->id, 'create_feed_text_post', 'feed_post', 101);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertSame(7, (int) $first->points);
        $this->assertSame(7, (int) $participation->fresh()->points_total);
        $this->assertSame(1, GamificationPointEvent::query()
            ->withoutGlobalScope('organization')
            ->where('participation_id', $participation->id)
            ->count());
    }

    public function test_scorer_noops_when_disabled(): void
    {
        $this->actingAsOrganizer();
        $event = $this->createEvent();
        [$eventId, $orgId] = $this->eventIds($event['id']);

        Gamification::query()->withoutGlobalScope('organization')->updateOrCreate(
            ['event_id' => $eventId],
            [
                'organization_id' => $orgId,
                'enabled' => false,
                'scores' => ['create_feed_text_post' => 10],
            ],
        );

        $participation = $this->makeParticipation($eventId, $orgId);

        $result = app(GamificationScorer::class)->awardNow(
            $orgId,
            $eventId,
            (int) $participation->id,
            'create_feed_text_post',
            'feed_post',
            55,
        );

        $this->assertNull($result);
        $this->assertSame(0, (int) $participation->fresh()->points_total);
    }

    /** @return array{0: int, 1: int} */
    private function eventIds(string $uuid): array
    {
        $row = DB::table('events')->where('uuid', $uuid)->first(['id', 'organization_id']);

        return [(int) $row->id, (int) $row->organization_id];
    }

    private function makeParticipation(int $eventId, int $orgId): Participation
    {
        $contact = Contact::query()->withoutGlobalScope('organization')->create([
            'organization_id' => $orgId,
            'email' => 'gamer'.uniqid('', true).'@example.com',
            'first_name' => 'Gamer',
            'last_name' => 'One',
        ]);

        return Participation::query()->withoutGlobalScope('organization')->forceCreate([
            'event_id' => $eventId,
            'organization_id' => $orgId,
            'contact_id' => $contact->id,
            'role' => 'attendee',
            'status' => 'registered',
            'points_total' => 0,
        ]);
    }
}
