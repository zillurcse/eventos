<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Services\Billing\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plan feature gate: `->middleware('feature:module.networking')`.
 * Checks the active organization's plan entitlements (architecture §6.2).
 */
class EnsureFeature
{
    public function __construct(protected TenantContext $tenant, protected FeatureGate $gate) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $orgId = $this->tenant->id();
        abort_unless($orgId, 403, 'No active organization for this request.');

        $org = Organization::find($orgId);

        abort_unless($org && $this->gate->allows($org, $feature), 403, "Your plan does not include: {$feature}");

        return $next($request);
    }
}
