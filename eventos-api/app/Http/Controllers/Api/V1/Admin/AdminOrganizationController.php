<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super-admin organization governance (architecture §2.1). Cross-tenant: reads
 * run on the migrator (BYPASSRLS) connection.
 */
class AdminOrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgs = Organization::on('pgsql_admin')
            ->withCount(['events', 'memberships', 'contacts'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $orgs->map(fn (Organization $o) => [
                'id' => $o->uuid,
                'name' => $o->name,
                'slug' => $o->slug,
                'status' => $o->status,
                'events' => $o->events_count,
                'members' => $o->memberships_count,
                'contacts' => $o->contacts_count,
                'created_at' => $o->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        $org = Organization::on('pgsql_admin')
            ->withCount(['events', 'memberships', 'contacts'])
            ->where('uuid', $uuid)->firstOrFail();

        $sub = Subscription::on('pgsql_admin')
            ->where('organization_id', $org->id)->with('plan')->latest('id')->first();

        return response()->json(['data' => [
            'id' => $org->uuid,
            'name' => $org->name,
            'status' => $org->status,
            'events' => $org->events_count,
            'members' => $org->memberships_count,
            'contacts' => $org->contacts_count,
            'plan' => $sub?->plan?->name,
            'subscription_status' => $sub?->status,
        ]]);
    }

    /** Suspend / verify / archive a tenant. */
    public function update(string $uuid, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,suspended,pending,archived'],
        ]);

        $org = Organization::on('pgsql_admin')->where('uuid', $uuid)->firstOrFail();
        // status is privileged (not $fillable) — super-admin governance only.
        $org->forceFill(['status' => $data['status']])->save();

        return response()->json(['data' => ['id' => $org->uuid, 'status' => $org->status]]);
    }
}
