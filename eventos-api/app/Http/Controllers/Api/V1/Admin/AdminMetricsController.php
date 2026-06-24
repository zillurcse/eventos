<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide dashboard (architecture §2.1). All aggregates run on the
 * migrator (BYPASSRLS) connection to span every tenant.
 */
class AdminMetricsController extends Controller
{
    public function index(): JsonResponse
    {
        $admin = DB::connection('pgsql_admin');

        return response()->json(['data' => [
            'organizations' => Organization::on('pgsql_admin')->count(),
            'organizations_by_status' => Organization::on('pgsql_admin')
                ->select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status'),
            'active_subscriptions' => Subscription::on('pgsql_admin')
                ->whereIn('status', ['active', 'trialing'])->count(),
            'revenue_cents' => (int) $admin->table('invoices')->where('status', 'paid')->sum('total_cents'),
            'events' => $admin->table('events')->whereNull('deleted_at')->count(),
            'attendees' => $admin->table('participations')->where('role', 'attendee')->whereNull('deleted_at')->count(),
            'recent_audit' => $admin->table('audit_logs')
                ->orderByDesc('created_at')->limit(5)
                ->get(['event', 'auditable_type', 'created_at'])
                ->map(fn ($a) => [
                    'event' => $a->event,
                    'entity' => class_basename($a->auditable_type ?? ''),
                    'at' => $a->created_at,
                ]),
        ]]);
    }
}
