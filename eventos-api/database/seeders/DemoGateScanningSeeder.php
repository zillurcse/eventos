<?php

namespace Database\Seeders;

use App\Models\CheckIn;
use App\Models\CheckInStation;
use App\Models\Event;
use App\Models\Participation;
use Illuminate\Database\Seeder;

/**
 * Demo gate-scanning data. For every event that has registered attendees but
 * no venue gates yet, seeds three entrance gates and scatters entry/exit
 * scans over the last three days so Onsite › Gates Scanning has live numbers
 * (a share of attendees is deliberately left unscanned to feed the no-show list).
 *
 * Runs on pgsql_admin (bypasses RLS); organization_id is set explicitly.
 *
 *   php artisan db:seed --class=DemoGateScanningSeeder --database=pgsql_admin
 */
class DemoGateScanningSeeder extends Seeder
{
    private const CONN = 'pgsql_admin';

    /** name, location, staff, kiosks, traffic weight */
    private const GATES = [
        ['Gate 1 - Main', 'Hall A', 2, 1, 50],
        ['Gate 3 - VIP', 'Hall B', 3, 0, 30],
        ['Gate 2 - North', 'Outdoor / Entrance', 0, 3, 20],
    ];

    public function run(): void
    {
        $events = Event::on(self::CONN)
            ->whereHas('participations', fn ($q) => $q->where('role', 'attendee'))
            ->get();

        foreach ($events as $event) {
            $hasGates = CheckInStation::on(self::CONN)
                ->where('event_id', $event->id)->where('type', 'entrance')->exists();
            if ($hasGates) {
                continue;
            }

            $gates = collect(self::GATES)->map(function (array $g) use ($event) {
                $gate = new CheckInStation;
                $gate->setConnection(self::CONN);
                $gate->forceFill([
                    'organization_id' => $event->organization_id,
                    'event_id' => $event->id,
                    'name' => $g[0],
                    'location' => $g[1],
                    'type' => 'entrance',
                    'meta' => ['staff' => $g[2], 'kiosks' => $g[3], 'direction' => 'both', 'reentry' => 'unlimited'],
                ])->save();

                return ['id' => $gate->id, 'weight' => $g[4]];
            });

            $attendees = Participation::on(self::CONN)
                ->where('event_id', $event->id)->where('role', 'attendee')->get();

            $scans = 0;
            foreach ($attendees as $i => $p) {
                if ($i % 4 === 3) {
                    continue; // every 4th attendee never shows up
                }

                $day = now()->subDays([0, 0, 1, 2][$i % 4])->startOfDay();
                $in = $day->copy()->setTime(rand(9, 13), rand(0, 59));
                $gateId = $this->pickGate($gates);

                $this->scan($event, $p, $gateId, 'in', $in);
                $p->newQuery()->whereKey($p->id)
                    ->update(['checked_in_at' => $in, 'status' => 'checked_in']);
                $scans++;

                if ($i % 2 === 0) { // half of them scanned out again
                    $this->scan($event, $p, $gateId, 'out', $in->copy()->addHours(rand(2, 5)));
                    $scans++;
                }
            }

            $this->command?->info("Event {$event->name}: 3 gates, {$scans} scans across {$attendees->count()} attendees.");
        }
    }

    private function pickGate($gates): int
    {
        $roll = rand(1, 100);
        $acc = 0;
        foreach ($gates as $g) {
            $acc += $g['weight'];
            if ($roll <= $acc) {
                return $g['id'];
            }
        }

        return $gates->last()['id'];
    }

    private function scan(Event $event, Participation $p, int $gateId, string $direction, $at): void
    {
        $scan = new CheckIn;
        $scan->setConnection(self::CONN);
        $scan->forceFill([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'participation_id' => $p->id,
            'station_id' => $gateId,
            'direction' => $direction,
            'scanned_at' => $at,
        ])->save();
    }
}
