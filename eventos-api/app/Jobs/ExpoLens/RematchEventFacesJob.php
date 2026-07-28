<?php

namespace App\Jobs\ExpoLens;

use App\Models\ExpoLensPhoto;
use App\Services\ExpoLens\FaceMatcher;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Re-runs matching for a whole event using the face vectors already stored on
 * expolens_photo_faces — no face-service calls, no re-detection.
 *
 * This is the cheap counterpart to ReprocessEventFacesJob, which re-detects
 * every photo from scratch. Prefer this whenever only the enrolment set or the
 * match threshold changed.
 */
class RematchEventFacesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(
        public int $organizationId,
        public int $eventId,
    ) {
        $this->onQueue('media');
    }

    public function handle(FaceMatcher $matcher, TenantContext $tenant): void
    {
        $tenant->set($this->organizationId);
        DB::statement("set app.current_organization = '{$this->organizationId}'");

        ExpoLensPhoto::where('event_id', $this->eventId)
            ->where('processing_status', 'ready')
            ->whereNull('deleted_at')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($photos) use ($matcher) {
                foreach ($photos as $photo) {
                    // Unconfirmed matches are recomputed; anything an organizer
                    // confirmed by hand is left alone.
                    DB::table('expolens_photo_matches')
                        ->where('photo_id', $photo->id)
                        ->where('confirmed', false)
                        ->delete();

                    $matcher->matchPhoto($this->organizationId, $this->eventId, $photo->id);
                }
            });
    }
}
