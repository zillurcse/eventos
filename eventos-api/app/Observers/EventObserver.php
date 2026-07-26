<?php

namespace App\Observers;

use App\Models\Event;
use App\Services\ExpoLens\ExpoLensRetention;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class EventObserver
{
    public function deleted(Event $event): void
    {
        $tenant = app(TenantContext::class);
        $previous = $tenant->id();
        $tenant->set($event->organization_id);
        DB::statement("set app.current_organization = '{$event->organization_id}'");

        try {
            app(ExpoLensRetention::class)->purgeEvent(
                (int) $event->organization_id,
                (int) $event->id,
            );
        } finally {
            $tenant->set($previous);
            if ($previous) {
                DB::statement("set app.current_organization = '{$previous}'");
            }
        }
    }
}
