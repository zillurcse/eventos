<?php

namespace App\Jobs\ExpoLens;

use App\Services\ExpoLens\FaceMatcher;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Back-fills one attendee's matches against every photo already processed for
 * the event. Runs after enrolment so late joiners don't need a full reprocess.
 */
class MatchParticipationFacesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $organizationId,
        public int $eventId,
        public int $participationId,
    ) {
        $this->onQueue('media');
    }

    public function handle(FaceMatcher $matcher, TenantContext $tenant): void
    {
        $tenant->set($this->organizationId);
        DB::statement("set app.current_organization = '{$this->organizationId}'");

        $matcher->matchParticipation($this->organizationId, $this->eventId, $this->participationId);
    }
}
