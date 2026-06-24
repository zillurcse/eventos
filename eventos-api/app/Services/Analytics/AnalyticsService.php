<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\DB;

/**
 * Append-only analytics fact ingestion (architecture §6.11). analytics_events
 * is RANGE-partitioned by occurred_at; inserts are RLS-scoped via the GUC.
 */
class AnalyticsService
{
    public function record(
        int $organizationId,
        ?int $eventId,
        string $type,
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $properties = [],
    ): void {
        DB::table('analytics_events')->insert([
            'organization_id' => $organizationId,
            'event_id' => $eventId,
            'type' => $type,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'properties' => $properties ? json_encode($properties) : null,
            'occurred_at' => now(),
        ]);
    }
}
