<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RBAC gate: `->middleware('perm:events.manage')`. Platform staff bypass.
 * Resolves the user's roles in the active org → permissions.
 */
class EnsurePermission
{
    public function __construct(protected TenantContext $tenant) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless($user, 401);

        if ($user->isPlatformStaff()) {
            return $next($request);
        }

        $orgId = $this->tenant->id();
        abort_unless($orgId, 403, 'No active organization for this request.');

        $membership = Membership::where('organization_id', $orgId)
            ->where('user_id', $user->id)
            ->first();

        $has = $membership && $membership->roles()
            ->whereHas('permissions', fn ($q) => $q->where('key', $permission))
            ->exists();

        abort_unless($has, 403, "Missing permission: {$permission}");

        return $next($request);
    }
}
