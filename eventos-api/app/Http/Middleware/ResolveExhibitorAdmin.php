<?php

namespace App\Http\Middleware;

use App\Models\Contact;
use App\Models\Exhibitor;
use App\Models\ExhibitorMember;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the exhibitor a signed-in user administers, then activates THAT
 * exhibitor's organization context (architecture §6.3). Like ResolveTenant, the
 * identity lookups run on the migrator connection (no tenant GUC yet), scoped
 * to the user's own contact/membership.
 */
class ResolveExhibitorAdmin
{
    public function __construct(protected TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $contactIds = Contact::on('pgsql_admin')->where('user_id', $user->id)->pluck('id');

        // Both exhibitor admins AND staff can self-serve (same rights). When the
        // user staffs several exhibitors, X-Exhibitor-Id (an exhibitor uuid) narrows it.
        $query = ExhibitorMember::on('pgsql_admin')
            ->whereIn('contact_id', $contactIds)
            ->whereIn('role', ['admin', 'staff']);

        if ($requested = $request->header('X-Exhibitor-Id')) {
            $exhibitorId = Exhibitor::on('pgsql_admin')->where('uuid', $requested)->value('id');
            $query->where('exhibitor_id', $exhibitorId);
        }

        $member = $query->first();

        abort_unless($member, 403, 'You are not an exhibitor team member.');

        $exhibitor = Exhibitor::on('pgsql_admin')->findOrFail($member->exhibitor_id);

        // A suspended exhibitor's admin login is locked out (super-admin governance).
        abort_if($exhibitor->status === 'suspended', 403, 'This exhibitor account has been suspended.');

        $this->tenant->set($exhibitor->organization_id);
        DB::statement("set app.current_organization = '{$exhibitor->organization_id}'");

        $request->attributes->set('exhibitor_id', $exhibitor->id);
        // The acting member — used to attribute replies / assignments.
        $request->attributes->set('exhibitor_member_id', $member->id);

        return $next($request);
    }
}
