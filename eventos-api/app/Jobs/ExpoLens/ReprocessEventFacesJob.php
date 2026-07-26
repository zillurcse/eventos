<?php

namespace App\Jobs\ExpoLens;

use App\Models\ExpoLensPhoto;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ReprocessEventFacesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $organizationId,
        public int $eventId,
    ) {
        $this->onQueue('default');
    }

    public function handle(TenantContext $tenant): void
    {
        $tenant->set($this->organizationId);
        DB::statement("set app.current_organization = '{$this->organizationId}'");

        ExpoLensPhoto::where('event_id', $this->eventId)
            ->whereNull('deleted_at')
            ->select('id')
            ->chunkById(100, function ($photos) {
                foreach ($photos as $photo) {
                    ProcessExpoLensPhotoJob::dispatch($this->organizationId, $photo->id);
                }
            });
    }
}
