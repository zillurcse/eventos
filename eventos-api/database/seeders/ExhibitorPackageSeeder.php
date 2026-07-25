<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Exhibitor;
use App\Models\ExhibitorPackage;
use Illuminate\Database\Seeder;

/**
 * Demo exhibitor packages (Platinum / Gold / Silver / Standard / Title Sponsor)
 * for every event that does not have any yet. Entitlements match the Showcase
 * catalogue in admin › Packages (key / enabled / limit).
 *
 * Idempotent: skips events that already have packages; within an event,
 * updateOrCreate by name so re-runs refresh prices/entitlements without dupes.
 *
 * Runs on `pgsql_admin` so it bypasses RLS; organization_id is set explicitly.
 *
 *   php artisan db:seed --class=ExhibitorPackageSeeder --database=pgsql_admin
 */
class ExhibitorPackageSeeder extends Seeder
{
    private const CONN = 'pgsql_admin';

    public function run(): void
    {
        $created = 0;
        $skipped = 0;
        $assigned = 0;

        foreach (Event::on(self::CONN)->get() as $event) {
            $hadAny = ExhibitorPackage::on(self::CONN)
                ->where('event_id', $event->id)
                ->exists();

            foreach ($this->catalogue() as $i => $row) {
                $pkg = ExhibitorPackage::on(self::CONN)->updateOrCreate(
                    [
                        'event_id' => $event->id,
                        'name' => $row['name'],
                    ],
                    [
                        'organization_id' => $event->organization_id,
                        'kind' => $row['kind'],
                        'price_cents' => $row['price_cents'],
                        'currency' => $row['currency'],
                        'entitlements' => $row['entitlements'],
                        'rank' => $row['rank'],
                        'sort_order' => $i,
                    ],
                );

                if ($pkg->wasRecentlyCreated) {
                    $created++;
                }
            }

            if ($hadAny) {
                $skipped++;
            }

            $assigned += $this->assignMissingPackages($event->id);
        }

        $this->command?->info(
            "Exhibitor packages: {$created} created"
            .($skipped ? ", {$skipped} event(s) already had packages (refreshed by name)" : '')
            .", {$assigned} exhibitor(s) assigned a default package."
        );
    }

    /**
     * Booths with no package_id get Gold (or the highest-ranked package), so the
     * Permissions tab and exhibitor portal have something to resolve against.
     * Also freeze that package's entitlements into profile_data when missing.
     */
    private function assignMissingPackages(int $eventId): int
    {
        $default = ExhibitorPackage::on(self::CONN)
            ->where('event_id', $eventId)
            ->where('name', 'Gold')
            ->first()
            ?? ExhibitorPackage::on(self::CONN)
                ->where('event_id', $eventId)
                ->orderByDesc('rank')
                ->first();

        if (! $default) {
            return 0;
        }

        $entitlements = is_array($default->entitlements) ? $default->entitlements : [];
        $assigned = 0;

        $booths = Exhibitor::on(self::CONN)
            ->where('event_id', $eventId)
            ->whereNull('package_id')
            ->get();

        foreach ($booths as $booth) {
            $profile = is_array($booth->profile_data) ? $booth->profile_data : [];
            $saved = $profile['entitlements'] ?? null;
            $missingEntitlements = ! is_array($saved) || $saved === [];

            $cols = ['package_id' => $default->id];
            if ($missingEntitlements && $entitlements !== []) {
                $profile['entitlements'] = $entitlements;
                $cols['profile_data'] = $profile;
            }

            $booth->forceFill($cols)->save();
            $assigned++;
        }

        return $assigned;
    }

    /**
     * Tier ladder used across demo events.
     *
     * @return list<array{name: string, kind: string, price_cents: int, currency: string, rank: int, entitlements: list<array{key: string, enabled: bool, limit: int}>}>
     */
    private function catalogue(): array
    {
        return [
            [
                'name' => 'Platinum',
                'kind' => 'both',
                'price_cents' => 1500000,
                'currency' => 'USD',
                'rank' => 100,
                'entitlements' => $this->entitlements([
                    'teams' => 10,
                    'projects' => 10,
                    'products' => 25,
                    'documents' => 25,
                    'videos' => 10,
                    'cta' => 5,
                    'meetings' => 50,
                    'lounge' => 5,
                    'all_leads' => true,
                    'team_connections' => true,
                    'recommended_leads' => true,
                    'lead_qualification' => true,
                    'lead_analytics' => true,
                    'lead_export' => true,
                    'analytics' => true,
                ]),
            ],
            [
                'name' => 'Gold',
                'kind' => 'both',
                'price_cents' => 750000,
                'currency' => 'USD',
                'rank' => 80,
                'entitlements' => $this->entitlements([
                    'teams' => 5,
                    'projects' => 5,
                    'products' => 15,
                    'documents' => 15,
                    'videos' => 5,
                    'cta' => 3,
                    'meetings' => 25,
                    'lounge' => 3,
                    'all_leads' => true,
                    'team_connections' => true,
                    'recommended_leads' => true,
                    'lead_qualification' => true,
                    'lead_analytics' => true,
                    'lead_export' => false,
                    'analytics' => true,
                ]),
            ],
            [
                'name' => 'Silver',
                'kind' => 'exhibitor',
                'price_cents' => 350000,
                'currency' => 'USD',
                'rank' => 60,
                'entitlements' => $this->entitlements([
                    'teams' => 3,
                    'projects' => 3,
                    'products' => 8,
                    'documents' => 8,
                    'videos' => 2,
                    'cta' => 2,
                    'meetings' => 10,
                    'lounge' => 1,
                    'all_leads' => true,
                    'team_connections' => true,
                    'recommended_leads' => false,
                    'lead_qualification' => true,
                    'lead_analytics' => false,
                    'lead_export' => false,
                    'analytics' => false,
                ]),
            ],
            [
                'name' => 'Standard Booth',
                'kind' => 'exhibitor',
                'price_cents' => 150000,
                'currency' => 'USD',
                'rank' => 40,
                'entitlements' => $this->entitlements([
                    'teams' => 2,
                    'projects' => 1,
                    'products' => 5,
                    'documents' => 5,
                    'videos' => 1,
                    'cta' => 1,
                    'meetings' => 5,
                    'lounge' => 0,
                    'all_leads' => true,
                    'team_connections' => false,
                    'recommended_leads' => false,
                    'lead_qualification' => false,
                    'lead_analytics' => false,
                    'lead_export' => false,
                    'analytics' => false,
                ]),
            ],
            [
                'name' => 'Title Sponsor',
                'kind' => 'sponsor',
                'price_cents' => 2500000,
                'currency' => 'USD',
                'rank' => 120,
                'entitlements' => $this->entitlements([
                    'teams' => 8,
                    'projects' => 5,
                    'products' => 10,
                    'documents' => 10,
                    'videos' => 8,
                    'cta' => 5,
                    'meetings' => 30,
                    'lounge' => 4,
                    'all_leads' => true,
                    'team_connections' => true,
                    'recommended_leads' => true,
                    'lead_qualification' => true,
                    'lead_analytics' => true,
                    'lead_export' => true,
                    'analytics' => true,
                ]),
            ],
        ];
    }

    /**
     * Build the FeatureLine[] payload the admin Packages UI expects.
     *
     * Countable features: int limit (0 = disabled). Bool features: on/off only.
     *
     * @param  array<string, int|bool>  $spec
     * @return list<array{key: string, enabled: bool, limit: int}>
     */
    private function entitlements(array $spec): array
    {
        $countable = [
            'teams', 'projects', 'products', 'documents', 'videos',
            'cta', 'meetings', 'lounge',
        ];
        $toggles = [
            'all_leads', 'team_connections', 'recommended_leads',
            'lead_qualification', 'lead_analytics', 'lead_export', 'analytics',
        ];

        $out = [];
        foreach ($countable as $key) {
            $limit = (int) ($spec[$key] ?? 0);
            $out[] = ['key' => $key, 'enabled' => $limit > 0, 'limit' => max(0, $limit)];
        }
        foreach ($toggles as $key) {
            $on = (bool) ($spec[$key] ?? false);
            $out[] = ['key' => $key, 'enabled' => $on, 'limit' => 0];
        }

        return $out;
    }
}
