<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanChangeRequestResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /** Current subscription (tenant-scoped by the global scope + RLS). */
    public function current(): JsonResponse
    {
        $sub = Subscription::with('plan')->latest('id')->first();

        return response()->json(['data' => $sub ? new SubscriptionResource($sub) : null]);
    }

    /** The organization's open (pending) plan-change request, if any. */
    public function changeRequest(): JsonResponse
    {
        $req = PlanChangeRequest::with('requestedPlan', 'currentPlan')
            ->where('status', 'pending')->latest('id')->first();

        return response()->json(['data' => $req ? new PlanChangeRequestResource($req) : null]);
    }

    /**
     * Request a plan change. Instead of switching immediately, this records a
     * pending request that a platform super-admin approves (which activates the
     * plan) or rejects. One open request per organization.
     */
    public function requestChange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', 'exists:plans,slug'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $plan = Plan::where('slug', $data['plan'])->firstOrFail();
        $sub = Subscription::with('plan')->latest('id')->first();

        abort_if(
            $sub && (int) $sub->plan_id === (int) $plan->id,
            422,
            'You are already on this plan.',
        );

        // Reuse the open request if one exists (change the target), else create.
        $req = PlanChangeRequest::where('status', 'pending')->latest('id')->first()
            ?? new PlanChangeRequest;

        $req->fill([
            'subscription_id' => $sub?->id,
            'current_plan_id' => $sub?->plan_id,
            'requested_plan_id' => $plan->id,
            'note' => $data['note'] ?? null,
            'requested_by' => $request->user()->id,
        ]);
        $req->forceFill(['status' => 'pending'])->save();

        return response()->json([
            'data' => new PlanChangeRequestResource($req->load('requestedPlan', 'currentPlan')),
        ], 201);
    }

    /** Withdraw the organization's open plan-change request. */
    public function cancelChangeRequest(): JsonResponse
    {
        $req = PlanChangeRequest::where('status', 'pending')->latest('id')->first();

        if ($req) {
            $req->forceFill(['status' => 'canceled'])->save();
        }

        return response()->json(['data' => null]);
    }
}
