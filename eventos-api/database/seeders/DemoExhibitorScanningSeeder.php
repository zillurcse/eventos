<?php

namespace Database\Seeders;

use App\Models\CheckIn;
use App\Models\CheckInStation;
use App\Models\Event;
use App\Models\Exhibitor;
use App\Models\Participation;
use Illuminate\Database\Seeder;

/**
 * Demo exhibitor booth-scanning data. For every event that has exhibitors and
 * registered attendees but no booth stations yet, seeds a booth per exhibitor
 * (check_in_stations type "booth") and scatters visit scans over the last three
 * days so Onsite › Exhibitors Scanning has live footfall — busier booths get
 * proportionally more traffic to make the leaderboard and heatmap read well.
 *
 * Runs on pgsql_admin (bypasses RLS); organization_id is set explicitly.
 *
 *   php artisan db:seed --class=DemoExhibitorScanningSeeder --database=pgsql_admin
 */
class DemoExhibitorScanningSeeder extends Seeder
{
    private const CONN = 'pgsql_admin';

    private const HALLS = ['Hall A', 'Hall B', 'Hall C'];

    private const SCAN_MODES = ['staff_kiosk', 'staff', 'kiosk'];

    public function run(): void
    {
        $events = Event::on(self::CONN)
            ->whereHas('participations', fn ($q) => $q->where('role', 'attendee'))
            ->get();

        foreach ($events as $event) {
            $hasBooths = CheckInStation::on(self::CONN)
                ->where('event_id', $event->id)->where('type', 'booth')->exists();
            if ($hasBooths) {
                continue;
            }

            $exhibitors = Exhibitor::on(self::CONN)
                ->where('event_id', $event->id)->where('type', 'exhibitor')
                ->orderBy('id')->take(6)->get();
            if ($exhibitors->isEmpty()) {
                continue;
            }

            // One booth per exhibitor; earlier (bigger) exhibitors pull more traffic.
            $booths = $exhibitors->values()->map(function (Exhibitor $ex, int $i) use ($event) {
                $code = chr(65 + ($i % 3)).'-'.str_pad((string) (($i + 1) * 4), 2, '0', STR_PAD_LEFT);

                $booth = new CheckInStation;
                $booth->setConnection(self::CONN);
                $booth->forceFill([
                    'organization_id' => $event->organization_id,
                    'event_id' => $event->id,
                    'name' => $code,
                    'location' => self::HALLS[$i % 3],
                    'type' => 'booth',
                    'meta' => [
                        'exhibitor_id' => $ex->id,
                        'exhibitor_name' => $ex->name,
                        'scan_mode' => self::SCAN_MODES[$i % 3],
                        'lead_generation' => $i % 2 === 0,
                    ],
                ])->save();

                return ['id' => $booth->id, 'weight' => max(1, 6 - $i)];
            });

            $attendees = Participation::on(self::CONN)
                ->where('event_id', $event->id)->where('role', 'attendee')->get();

            $scans = 0;
            foreach ($attendees as $p) {
                $visits = rand(0, 4); // some attendees roam more booths than others
                for ($v = 0; $v < $visits; $v++) {
                    $boothId = $this->pickBooth($booths);
                    $day = now()->subDays(rand(0, 2))->startOfDay();
                    $at = $day->copy()->setTime(rand(9, 16), rand(0, 59));
                    $this->scan($event, $p, $boothId, $at);
                    $scans++;
                }
            }

            $this->command?->info("Event {$event->name}: {$booths->count()} booths, {$scans} booth scans across {$attendees->count()} attendees.");
        }
    }

    private function pickBooth($booths): int
    {
        $total = $booths->sum('weight');
        $roll = rand(1, $total);
        $acc = 0;
        foreach ($booths as $b) {
            $acc += $b['weight'];
            if ($roll <= $acc) {
                return $b['id'];
            }
        }

        return $booths->last()['id'];
    }

    private function scan(Event $event, Participation $p, int $boothId, $at): void
    {
        $scan = new CheckIn;
        $scan->setConnection(self::CONN);
        $scan->forceFill([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'participation_id' => $p->id,
            'station_id' => $boothId,
            'direction' => 'in',
            'scanned_at' => $at,
        ])->save();
    }
}
