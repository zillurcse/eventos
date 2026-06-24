<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\PlanResource;
use App\Models\Organization;
use App\Services\Billing\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function current(Request $request, TenantContext $tenant, FeatureGate $gate): JsonResponse
    {
        $org = Organization::findOrFail($tenant->id());
        $plan = $gate->plan($org);

        return response()->json([
            'data' => (new OrganizationResource($org))->resolve($request),
            'plan' => $plan ? (new PlanResource($plan))->resolve($request) : null,
            'features' => $gate->features($org),
        ]);
    }
}
