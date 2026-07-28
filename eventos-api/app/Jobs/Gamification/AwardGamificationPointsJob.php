<?php

namespace App\Jobs\Gamification;

use App\Services\Gamification\GamificationScorer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Background award of gamification points for one participant action.
 * Runs on the default queue so feed/session writes stay fast.
 */
class AwardGamificationPointsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public int $organizationId,
        public int $eventId,
        public int $participationId,
        public string $actionKey,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
        public array $meta = [],
    ) {
        $this->onQueue('default');
    }

    public function handle(GamificationScorer $scorer): void
    {
        $scorer->awardNow(
            $this->organizationId,
            $this->eventId,
            $this->participationId,
            $this->actionKey,
            $this->subjectType,
            $this->subjectId,
            $this->meta,
        );
    }
}
