<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\ExpoLens\ExpoLensRetention;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeExpoLensEvent extends Command
{
    protected $signature = 'expolens:purge-event {uuid} {--force}';

    protected $description = 'Delete ExpoLens photos and biometric face data for one event.';

    public function handle(ExpoLensRetention $retention, TenantContext $tenant): int
    {
        $event = Event::on('pgsql_admin')->where('uuid', $this->argument('uuid'))->first();
        if (! $event) {
            $this->error('Event not found.');

            return self::FAILURE;
        }

        if (! $this->option('force')
            && ! $this->confirm("Purge ExpoLens biometric data for {$event->name}?")) {
            return self::SUCCESS;
        }

        $tenant->set($event->organization_id);
        DB::statement("set app.current_organization = '{$event->organization_id}'");
        $retention->purgeEvent((int) $event->organization_id, (int) $event->id);
        $this->info('ExpoLens data purged.');

        return self::SUCCESS;
    }
}
