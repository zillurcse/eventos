<?php

namespace App\Services\Analytics;

use App\Models\Event;
use App\Models\ReportSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Rolls raw analytics_events into materialized report_snapshots for fast
 * dashboards, avoiding heavy aggregation on the transactional tables (§6.11).
 */
class ReportSnapshotService
{
    public function rollup(Event $event, string $metric = 'engagement'): ReportSnapshot
    {
        $byType = DB::table('analytics_events')
            ->where('event_id', $event->id)
            ->select('type', DB::raw('count(*) as c'))
            ->groupBy('type')
            ->pluck('c', 'type');

        return ReportSnapshot::create([
            'event_id' => $event->id,
            'metric' => $metric,
            'dimension' => 'by_type',
            'period_start' => now()->startOfDay(),
            'period_end' => now()->endOfDay(),
            'data' => $byType->toArray(),
            'generated_at' => now(),
        ]);
    }
}
