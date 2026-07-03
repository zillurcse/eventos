<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventSetting;
use App\Models\Organization;
use Database\Seeders\Concerns\SeedsEventContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Enriches the EXISTING published "aiexpo" event (AI Expo 2026) with the standard
 * microsite test dataset — speakers, featured sessions, sponsors, reception ads,
 * breakout rooms and five logins (4 attendees + 1 host, all password="password").
 *
 * Unlike DemoReceptionSeeder this does NOT create the event; it resolves it by its
 * `aiexpo` subdomain and adds to whatever is already there (its uploaded logo /
 * banner and existing exhibitors are preserved — only `social` is filled in).
 *
 * Run: php artisan db:seed --class=AiexpoSeeder
 */
class AiexpoSeeder extends Seeder
{
    use SeedsEventContent;

    private const SUBDOMAIN = 'aiexpo';

    public function run(): void
    {
        $prev = DB::getDefaultConnection();
        DB::setDefaultConnection('pgsql_admin');

        try {
            $lines = DB::transaction(fn () => $this->seed());
        } finally {
            DB::setDefaultConnection($prev);
        }

        if ($lines === null) {
            $this->command?->warn('No published event found for subdomain "'.self::SUBDOMAIN.'" — nothing seeded.');

            return;
        }

        $this->command?->info('aiexpo event seeded → http://localhost:3001/?subdomain=aiexpo');
        foreach ($lines as $line) {
            $this->command?->info($line);
        }
    }

    /** @return string[]|null */
    private function seed(): ?array
    {
        $setting = EventSetting::where('domain->subdomain', self::SUBDOMAIN)->first();
        if (! $setting) {
            return null;
        }

        $event = Event::find($setting->event_id);
        if (! $event || $event->status !== 'published') {
            return null;
        }

        $org = Organization::find($event->organization_id);

        // Fill in socials without disturbing the event's real uploaded branding.
        if (empty($setting->social)) {
            $setting->social = [
                'twitter' => 'https://twitter.com/aiexpo',
                'instagram' => 'https://instagram.com/aiexpo',
                'linkedin' => 'https://linkedin.com/company/aiexpo',
                'facebook' => 'https://facebook.com/aiexpo',
            ];
        }
        if (empty($setting->login)) {
            $setting->login = ['methods' => ['email'], 'require_login' => false];
        }
        $setting->save();

        return $this->seedStandardContent($org, $event, now(), 'aiexpo.test', 'ai');
    }
}
