<?php

namespace App\Services\Tenancy;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provisions a brand-new tenant: user → organization → settings → owner
 * membership (owner role) → Free subscription. Org-scoped rows are written
 * with the tenant GUC active so Postgres RLS WITH CHECK is satisfied.
 */
class OrganizationProvisioner
{
    public function __construct(protected TenantContext $tenant) {}

    /**
     * @return array{user: User, organization: Organization, membership: Membership}
     */
    public function register(string $name, string $email, string $password, string $orgName): array
    {
        return DB::transaction(function () use ($name, $email, $password, $orgName) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,           // hashed by cast
                'email_verified_at' => now(),
            ]);

            $org = new Organization([
                'name' => $orgName,
                'slug' => $this->uniqueSlug($orgName),
                'default_currency' => 'USD',
                'default_timezone' => 'UTC',
                'default_locale' => 'en',
                'billing_email' => $email,
            ]);
            // status + owner_user_id are privileged (not $fillable).
            $org->forceFill(['status' => 'active', 'owner_user_id' => $user->id])->save();

            // Activate tenant context so RLS accepts the org-scoped inserts and
            // BelongsToOrganization auto-fills organization_id.
            $this->tenant->set($org->id);
            DB::statement("set app.current_organization = '{$org->id}'");

            OrganizationSetting::create(['organization_id' => $org->id]);

            $membership = Membership::create([
                'user_id' => $user->id,
                'organization_id' => $org->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            if ($ownerRole = Role::whereNull('organization_id')->where('name', 'owner')->first()) {
                $membership->roles()->attach($ownerRole->id);
            }

            if ($free = Plan::where('slug', 'free')->first()) {
                // organization_id auto-fills from the tenant context set above;
                // status is privileged (not $fillable) → forceFill.
                $subscription = new Subscription([
                    'plan_id' => $free->id,
                    'gateway' => 'manual',
                    'quantity' => 1,
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonth(),
                ]);
                $subscription->forceFill(['status' => 'active'])->save();
            }

            return ['user' => $user, 'organization' => $org, 'membership' => $membership];
        });
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'org';
        $slug = $base;
        $i = 1;
        while (Organization::where('slug', $slug)->withTrashed()->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
