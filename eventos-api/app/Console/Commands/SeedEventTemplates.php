<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\Email\EventTemplateSeeder;
use Illuminate\Console\Command;

/**
 * Back-fills the 36 system email templates for existing events that were
 * created before auto-seeding was introduced.
 *
 * Usage:
 *   php artisan emails:seed-templates           # all events
 *   php artisan emails:seed-templates --event=UUID
 */
class SeedEventTemplates extends Command
{
    protected $signature = 'emails:seed-templates {--event= : Seed a single event by UUID}';

    protected $description = 'Seed default email templates for existing events';

    public function handle(EventTemplateSeeder $seeder): int
    {
        $query = Event::query()->with('organization');

        if ($uuid = $this->option('event')) {
            $query->where('uuid', $uuid);
        }

        $events = $query->get();

        if ($events->isEmpty()) {
            $this->warn('No events found.');
            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($events->count());
        $bar->start();

        foreach ($events as $event) {
            if (! $event->organization_id) {
                $bar->advance();
                continue;
            }

            $seeder->seedForEvent($event, $event->organization_id);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done — templates seeded for {$events->count()} event(s).");

        return self::SUCCESS;
    }
}
