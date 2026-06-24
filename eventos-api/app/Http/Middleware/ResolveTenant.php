<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active organization for the request and pins it in two places
 * (architecture §4.2):
 *   1. TenantContext  → the application global scope reads this.
 *   2. app.current_organization GUC on the DB connection → Postgres RLS reads this.
 *
 * Resolution order: the authenticated user's active membership (optionally
 * narrowed by an X-Organization-Id header when the user belongs to several orgs).
 */
class ResolveTenant
{
    public function __construct(protected TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $organizationId = $this->resolveOrganizationId($request);

        if ($organizationId !== null) {
            $this->tenant->set($organizationId);

            // DB backstop. Integer-cast above guarantees this is injection-safe.
            // (Session-level for dev; switch to SET LOCAL in a transaction once
            //  PgBouncer transaction pooling is introduced — architecture §11.1.)
            DB::statement("set app.current_organization = '{$organizationId}'");
        }

        return $next($request);
    }

    protected function resolveOrganizationId(Request $request): ?int
    {
        $requested = $request->header('X-Organization-Id');
        $requested = is_numeric($requested) ? (int) $requested : null;

        $user = $request->user();

        if ($user) {
            // Identity-plane read: which org? Runs on the migrator connection
            // (BYPASSRLS) but constrained to the user's own active memberships,
            // because the tenant GUC isn't set yet.
            $membership = Membership::on('pgsql_admin')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->when($requested, fn ($q) => $q->where('organization_id', $requested))
                ->first();

            if ($membership) {
                return (int) $membership->organization_id;
            }
        }

        return $requested;
    }
}
