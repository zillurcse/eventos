<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Super-admin management of organizer memberships within a tenant (architecture
 * §2.1, §6.1). Org-scoped tables (memberships, roles, membership_role) run on
 * the migrator (BYPASSRLS) connection; the global `users` table is created on
 * the default connection.
 */
class AdminMembershipController extends Controller
{
    public function index(string $orgUuid): JsonResponse
    {
        $org = Organization::on('pgsql_admin')->where('uuid', $orgUuid)->firstOrFail();

        $members = Membership::on('pgsql_admin')
            ->with(['user', 'roles'])
            ->where('organization_id', $org->id)
            ->latest('id')
            ->get();

        return response()->json([
            'organization' => ['id' => $org->uuid, 'name' => $org->name],
            'data' => $members->map(fn (Membership $m) => $this->presentMembership($m)),
        ]);
    }

    /** Attach (or create) a user as an organizer of this org, with role(s). */
    public function store(string $orgUuid, Request $request): JsonResponse
    {
        $org = Organization::on('pgsql_admin')->where('uuid', $orgUuid)->firstOrFail();

        $data = $request->validate([
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:180'],
            'password' => ['nullable', 'string', 'min:8'],
            'status' => ['nullable', Rule::in(['active', 'invited', 'suspended'])],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer'],
            'role' => ['nullable', 'string'],
        ]);

        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'] ?? $data['email'],
                'password' => $data['password'] ?? Str::password(16),
                'email_verified_at' => now(),
            ],
        );

        // The unique (user_id, organization_id) index also covers soft-deleted
        // rows, so revive an old membership instead of inserting a duplicate.
        $membership = Membership::on('pgsql_admin')->withTrashed()
            ->where('user_id', $user->id)
            ->where('organization_id', $org->id)
            ->first();

        if ($membership) {
            if ($membership->trashed()) {
                $membership->restore();
            }
            $membership->update([
                'status' => $data['status'] ?? 'active',
                'joined_at' => $membership->joined_at ?? now(),
            ]);
        } else {
            $membership = new Membership([
                'user_id' => $user->id,
                'organization_id' => $org->id,
                'status' => $data['status'] ?? 'active',
                'joined_at' => now(),
            ]);
            $membership->setConnection('pgsql_admin');
            $membership->save();
        }

        $roleIds = $this->resolveRoleIds($org, $data);
        if ($roleIds->isNotEmpty()) {
            $membership->roles()->sync($roleIds);
        }

        return response()->json(['data' => $this->presentMembership($membership->fresh(['user', 'roles']))], 201);
    }

    public function update(string $orgUuid, int $membership, Request $request): JsonResponse
    {
        $org = Organization::on('pgsql_admin')->where('uuid', $orgUuid)->firstOrFail();

        $m = Membership::on('pgsql_admin')
            ->where('organization_id', $org->id)
            ->where('id', $membership)
            ->firstOrFail();

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['active', 'invited', 'suspended'])],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['integer'],
            'role' => ['sometimes', 'string'],
        ]);

        if (array_key_exists('status', $data)) {
            $m->update(['status' => $data['status']]);
        }

        if ($request->has('roles') || $request->has('role')) {
            $m->roles()->sync($this->resolveRoleIds($org, $data));
        }

        return response()->json(['data' => $this->presentMembership($m->fresh(['user', 'roles']))]);
    }

    public function destroy(string $orgUuid, int $membership): JsonResponse
    {
        $org = Organization::on('pgsql_admin')->where('uuid', $orgUuid)->firstOrFail();

        $m = Membership::on('pgsql_admin')
            ->where('organization_id', $org->id)
            ->where('id', $membership)
            ->firstOrFail();

        $m->roles()->detach();
        $m->delete();

        return response()->json(['message' => 'Membership removed.']);
    }

    /** Assignable roles: system tenant roles (org NULL) + any roles owned by the org. */
    public function roles(Request $request): JsonResponse
    {
        $orgId = null;
        if ($orgUuid = $request->query('organization')) {
            $orgId = Organization::on('pgsql_admin')->where('uuid', $orgUuid)->value('id');
        }

        $roles = Role::on('pgsql_admin')
            ->where('scope', 'tenant')
            ->where(fn ($q) => $q->whereNull('organization_id')
                ->when($orgId, fn ($w) => $w->orWhere('organization_id', $orgId)))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $roles->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'scope' => $r->scope,
                'is_system' => (bool) $r->is_system,
                'description' => $r->description,
            ]),
        ]);
    }

    // ── helpers ──────────────────────────────────────────────

    protected function resolveRoleIds(Organization $org, array $data): Collection
    {
        if (! empty($data['roles'])) {
            return Role::on('pgsql_admin')
                ->whereIn('id', $data['roles'])
                ->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $org->id))
                ->pluck('id');
        }

        if (! empty($data['role'])) {
            return Role::on('pgsql_admin')
                ->whereNull('organization_id')->where('scope', 'tenant')->where('name', $data['role'])
                ->pluck('id');
        }

        return collect();
    }

    protected function presentMembership(Membership $m): array
    {
        return [
            'id' => $m->id,
            'user' => [
                'id' => $m->user?->uuid,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'status' => $m->user?->status,
            ],
            'status' => $m->status,
            'roles' => $m->roles->map(fn (Role $r) => ['id' => $r->id, 'name' => $r->name])->values(),
            'joined_at' => $m->joined_at?->toIso8601String(),
        ];
    }
}
