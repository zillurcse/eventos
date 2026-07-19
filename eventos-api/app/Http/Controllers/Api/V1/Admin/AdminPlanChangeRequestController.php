<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanChangeRequestResource;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Services\Billing\BillingManager;
use App\Services\Billing\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super-admin review of organizer plan-change requests (architecture §2.1).
 * Cross-tenant: reads/writes run on the migrator (BYPASSRLS) connection.
 * Approving a request activates the requested plan via the billing gateway.
 */
class AdminPlanChangeRequestController extends Controller
{
    private const CONN = 'pgsql_admin';

    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status')->toString() ?: 'pending';

        $requests = PlanChangeRequest::on(self::CONN)
            ->with(['requestedPlan', 'currentPlan', 'organization', 'requester'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => PlanChangeRequestResource::collection($requests)->resolve(),
        ]);
    }

    /** Approve: activate the requested plan on the organization's subscription. */
    public function approve(string $uuid, BillingManager $billing, FeatureGate $gate, Request $request): JsonResponse
    {
        $req = PlanChangeRequest::on(self::CONN)->where('uuid', $uuid)
            ->where('status', 'pending')->firstOrFail();

        $plan = Plan::on(self::CONN)->findOrFail($req->requested_plan_id);

        $sub = Subscription::on(self::CONN)
            ->where('organization_id', $req->organization_id)->latest('id')->first();

        if ($sub) {
            $billing->changePlan($sub, $plan);
        } else {
            // No subscription yet — provision one on the approved plan.
            $sub = (new Subscription)->setConnection(self::CONN);
            $sub->forceFill([
                'organization_id' => $req->organization_id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'gateway' => 'manual',
                'quantity' => 1,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ])->save();
        }

        $req->forceFill([
            'status' => 'approved',
            'subscription_id' => $sub->id,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        // Drop any cached entitlements so the org sees the new plan immediately.
        $gate->flush();

        return response()->json([
            'data' => new PlanChangeRequestResource($req->load('requestedPlan', 'currentPlan', 'organization')),
        ]);
    }

    /** Reject: leave the current plan untouched, record the reason. */
    public function reject(string $uuid, Request $request): JsonResponse
    {
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $req = PlanChangeRequest::on(self::CONN)->where('uuid', $uuid)
            ->where('status', 'pending')->firstOrFail();

        $req->forceFill([
            'status' => 'rejected',
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        return response()->json([
            'data' => new PlanChangeRequestResource($req->load('requestedPlan', 'currentPlan', 'organization')),
        ]);
    }
}
