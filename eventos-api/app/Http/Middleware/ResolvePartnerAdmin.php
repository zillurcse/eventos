<?php

namespace App\Http\Middleware;

use App\Models\Contact;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the partner a signed-in user administers, then activates THAT
 * partner's organization context (architecture §6.3). Like ResolveTenant, the
 * identity lookups run on the migrator connection (no tenant GUC yet), scoped
 * to the user's own contact/membership.
 */
class ResolvePartnerAdmin
{
    public function __construct(protected TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $contactIds = Contact::on('pgsql_admin')->where('user_id', $user->id)->pluck('id');

        // Both partner admins AND staff can self-serve (same rights). When the
        // user staffs several partners, X-Partner-Id (a partner uuid) narrows it.
        $query = PartnerMember::on('pgsql_admin')
            ->whereIn('contact_id', $contactIds)
            ->whereIn('role', ['admin', 'staff']);

        if ($requested = $request->header('X-Partner-Id')) {
            $partnerId = Partner::on('pgsql_admin')->where('uuid', $requested)->value('id');
            $query->where('partner_id', $partnerId);
        }

        $member = $query->first();

        abort_unless($member, 403, 'You are not a partner team member.');

        $partner = Partner::on('pgsql_admin')->findOrFail($member->partner_id);

        // A suspended partner's admin login is locked out (super-admin governance).
        abort_if($partner->status === 'suspended', 403, 'This partner account has been suspended.');

        $this->tenant->set($partner->organization_id);
        DB::statement("set app.current_organization = '{$partner->organization_id}'");

        $request->attributes->set('partner_id', $partner->id);

        return $next($request);
    }
}
