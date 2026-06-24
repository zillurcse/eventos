<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Participation;
use App\Models\Session;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\ReportSnapshotService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /** Attendee-context fact ingestion (page_view, session_join, booth_visit…). */
    public function track(Request $request, AnalyticsService $analytics, TenantContext $tenant): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:60'],
            'subject_type' => ['nullable', 'string', 'max:60'],
            'subject_id' => ['nullable', 'integer'],
            'properties' => ['nullable', 'array'],
        ]);

        $analytics->record(
            $tenant->id(),
            $request->attributes->get('event_id'),
            $data['type'],
            'participation',
            $request->attributes->get('participation_id'),
            $data['subject_type'] ?? null,
            $data['subject_id'] ?? null,
            $data['properties'] ?? [],
        );

        return response()->json(['recorded' => true], 202);
    }

    /** Organizer dashboard summary. */
    public function summary(string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        return response()->json(['data' => [
            'registrations' => Participation::where('event_id', $event->id)->where('role', 'attendee')->count(),
            'checked_in' => Participation::where('event_id', $event->id)->whereNotNull('checked_in_at')->count(),
            'sessions' => Session::where('event_id', $event->id)->count(),
            'events_by_type' => DB::table('analytics_events')
                ->where('event_id', $event->id)
                ->select('type', DB::raw('count(*) as c'))
                ->groupBy('type')
                ->pluck('c', 'type'),
        ]]);
    }

    /** Materialize a report snapshot from the fact stream. */
    public function rollup(string $uuid, ReportSnapshotService $snapshots): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $snapshot = $snapshots->rollup($event);

        return response()->json(['data' => [
            'metric' => $snapshot->metric,
            'dimension' => $snapshot->dimension,
            'data' => $snapshot->data,
            'generated_at' => $snapshot->generated_at?->toIso8601String(),
        ]], 201);
    }
}
