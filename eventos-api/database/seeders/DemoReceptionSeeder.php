<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventSetting;
use App\Models\Organization;
use Database\Seeders\Concerns\SeedsEventContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A fully-populated demo event for the attendee microsite (RECEPTION + ROOMS).
 *
 * Local dev only. Creates one published event reachable at the `demo` subdomain
 * (http://localhost:3001/?subdomain=demo) with brand theme, hero banners, ads,
 * featured sessions + speakers, exhibitors, sponsors, breakout rooms, and five
 * logins (4 attendees + 1 host, all password="password").
 *
 * Run: php artisan db:seed --class=DemoReceptionSeeder
 *
 * Tenant tables are RLS-protected, so every write runs on the BYPASSRLS
 * `pgsql_admin` connection with organization_id set explicitly (no ambient
 * tenant in a CLI seeder). Shared content builders live in SeedsEventContent;
 * AiexpoSeeder reuses them against the existing aiexpo event.
 */
class DemoReceptionSeeder extends Seeder
{
    use SeedsEventContent;

    public function run(): void
    {
        $prev = DB::getDefaultConnection();
        DB::setDefaultConnection('pgsql_admin');

        try {
            $lines = DB::transaction(fn () => $this->seed());
        } finally {
            DB::setDefaultConnection($prev);
        }

        $this->command?->info('Demo event seeded → http://localhost:3001/?subdomain=demo');
        foreach ($lines as $line) {
            $this->command?->info($line);
        }
    }

    /** @return string[] */
    private function seed(): array
    {
        $now = now();

        $org = Organization::updateOrCreate(
            ['slug' => 'eventos-demo'],
            ['name' => 'EventOS Demo', 'status' => 'active', 'default_timezone' => 'Asia/Dhaka'],
        );

        $event = Event::updateOrCreate(
            ['organization_id' => $org->id, 'slug' => 'eventos-live-demo'],
            [
                'name' => 'EventOS Live Demo 2026',
                'description' => 'Three days of keynotes, hands-on workshops and networking for the '
                    .'events industry. Meet exhibitors, book 1:1 meetings, and explore what a modern '
                    .'hybrid event platform can do — this is a fully seeded demonstration event.',
                'format' => 'hybrid',
                'status' => 'published',
                'timezone' => 'Asia/Dhaka',
                'starts_at' => $now->copy()->startOfDay()->addHours(9),
                'ends_at' => $now->copy()->addDays(2)->startOfDay()->addHours(18),
                'is_public' => true,
                'meta' => ['location' => ['address' => 'Grand Convention Hall, Dhaka', 'url' => 'https://live.eventos.demo']],
            ],
        );

        EventSetting::updateOrCreate(
            ['event_id' => $event->id],
            [
                'organization_id' => $org->id,
                'domain' => ['subdomain' => 'demo'],
                'theme' => ['primary' => '#6352e7', 'accent' => '#22d3ee'],
                'branding' => [
                    'logo_url' => 'https://placehold.co/240x80/6352e7/FFFFFF/png?text=EventOS+Demo',
                    'banners' => [
                        $this->pic('eventos-hero-1', 1200, 360),
                        $this->pic('eventos-hero-2', 1200, 360),
                        $this->pic('eventos-hero-3', 1200, 360),
                    ],
                ],
                'social' => [
                    'twitter' => 'https://twitter.com/eventos',
                    'instagram' => 'https://instagram.com/eventos',
                    'linkedin' => 'https://linkedin.com/company/eventos',
                    'facebook' => 'https://facebook.com/eventos',
                ],
                'login' => ['methods' => ['email'], 'require_login' => false],
                'seo' => ['meta_title' => 'EventOS Live Demo 2026', 'meta_description' => 'A seeded demo event.'],
            ],
        );

        return $this->seedStandardContent($org, $event, $now, 'demo.test', 'demo');
    }
}
