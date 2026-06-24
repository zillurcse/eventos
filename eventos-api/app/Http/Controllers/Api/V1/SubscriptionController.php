<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\BillingManager;
use App\Services\Billing\FeatureGate;
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

    /** Change plan via the configured gateway (manual in dev). */
    public function change(Request $request, BillingManager $billing, FeatureGate $gate): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', 'exists:plans,slug'],
        ]);

        $plan = Plan::where('slug', $data['plan'])->firstOrFail();
        $sub = Subscription::latest('id')->firstOrFail();

        $sub = $billing->changePlan($sub, $plan);
        $gate->flush();

        return response()->json(['data' => new SubscriptionResource($sub->load('plan'))]);
    }
}
