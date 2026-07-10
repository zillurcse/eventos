<?php

namespace Database\Seeders;

use App\Models\Exhibitor;
use App\Models\ServiceItem;
use App\Models\ServiceOrder;
use App\Models\ServiceRequest;
use Illuminate\Database\Seeder;

/**
 * Demo service orders. For every event that has an active services catalogue,
 * seeds a few orders per exhibitor across the four order states the organizer's
 * Requested Services page shows: pending, approved, rejected and partial (a
 * mix of approved + pending lines in one order).
 *
 * Runs on pgsql_admin (bypasses RLS); organization_id is set explicitly.
 *
 *   php artisan db:seed --class=DemoServiceOrdersSeeder --database=pgsql_admin
 */
class DemoServiceOrdersSeeder extends Seeder
{
    private const CONN = 'pgsql_admin';

    /** Line statuses per demo order — the 4th mixes, producing a "partial" order. */
    private const PATTERNS = [
        ['pending', 'pending'],
        ['approved', 'approved'],
        ['approved', 'pending'],
        ['rejected'],
    ];

    public function run(): void
    {
        $catalogue = ServiceItem::on(self::CONN)
            ->where('is_active', true)
            ->get()
            ->groupBy('event_id');

        $orders = 0;
        foreach ($catalogue as $eventId => $grouped) {
            $items = $grouped->values();

            $exhibitors = Exhibitor::on(self::CONN)
                ->where('event_id', $eventId)
                ->where('type', 'exhibitor')
                ->orderBy('id')
                ->limit(3)
                ->get();

            foreach ($exhibitors as $index => $exhibitor) {
                if (ServiceOrder::on(self::CONN)->where('exhibitor_id', $exhibitor->id)->exists()) {
                    continue;
                }

                // Each booth gets a different slice of the state patterns.
                foreach (array_slice(self::PATTERNS, 0, $index + 2) as $slot => $statuses) {
                    $this->seedOrder($exhibitor, $items, $statuses, $slot);
                    $orders++;
                }
            }
        }

        $this->command?->info("Seeded {$orders} demo service orders.");
    }

    private function seedOrder(Exhibitor $exhibitor, $items, array $statuses, int $slot): void
    {
        $order = new ServiceOrder;
        $order->setConnection(self::CONN);
        $order->forceFill([
            'organization_id' => $exhibitor->organization_id,
            'event_id' => $exhibitor->event_id,
            'exhibitor_id' => $exhibitor->id,
            'order_number' => ServiceOrder::nextOrderNumber(),
            'submitted_at' => now()->subDays(14 - $slot * 3),
        ])->save();

        foreach ($statuses as $line => $status) {
            $item = $items[($slot + $line) % $items->count()];

            $request = new ServiceRequest;
            $request->setConnection(self::CONN);
            $request->forceFill([
                'organization_id' => $exhibitor->organization_id,
                'event_id' => $exhibitor->event_id,
                'exhibitor_id' => $exhibitor->id,
                'service_order_id' => $order->id,
                'service_item_id' => $item->id,
                'quantity' => $line + 1,
                'unit_price' => $item->rate,
                'currency' => $item->currency,
                'status' => $status,
            ])->save();
        }
    }
}
