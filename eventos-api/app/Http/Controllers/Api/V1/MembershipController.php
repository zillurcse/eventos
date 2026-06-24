<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Organizer self-service team management within their OWN org (architecture
 * §6.1). Runs on the default connection under the tenant GUC (set by
 * ResolveTenant), so Postgres RLS scopes every membership/role row to the active
 * org. Gated by perm:members.manage (owner role). The global `users` table has
 * no RLS, so logins are created there directly.
 */
class MembershipController extends Controller
{
    public function __construct(protected TenantContext $tenant) {}

    public function index(): JsonResponse
    {
        // RLS constrains memberships to the active org.
        $members = Membership::with(['user', 'roles'])->latest('id')->get();

        return response()->json(['data' => $members->map(fn (Membership $m) => $this->present($m))]);
    }

    public function store(Request $request): JsonResponse
    {
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

        $orgId = $this->tenant->id();

        // The unique (user_id, organization_id) index also covers soft-deleted
        // rows; revive instead of inserting a duplicate. organization_id is set
        // explicitly (Membership has no auto-fill) and equals the GUC, so the
        // RLS WITH CHECK passes.
        $membership = Membership::withTrashed()
            ->where('user_id', $user->id)
            ->where('organization_id', $orgId)
            ->first();

        if ($membership) {
            if ($membership->trashed()) {
                $membership->restore();
            }
            $membership->update(['status' => $data['status'] ?? 'active', 'joined_at' => $membership->joined_at ?? now()]);
        } else {
            $membership = Membership::create([
                'user_id' => $user->id,
                'organization_id' => $orgId,
                'status' => $data['status'] ?? 'active',
                'joined_at' => now(),
            ]);
        }

        $roleIds = $this->resolveRoleIds($orgId, $data);
        if ($roleIds->isNotEmpty()) {
            $membership->roles()->sync($roleIds);
        }

        return response()->json(['data' => $this->present($membership->fresh(['user', 'roles']))], 201);
    }

    public function update(int $membership, Request $request): JsonResponse
    {
        $m = Membership::findOrFail($membership);   // RLS 404s rows from other orgs

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
            $m->roles()->sync($this->resolveRoleIds($this->tenant->id(), $data));
        }

        return response()->json(['data' => $this->present($m->fresh(['user', 'roles']))]);
    }

    public function destroy(int $membership): JsonResponse
    {
        $m = Membership::findOrFail($membership);
        $m->roles()->detach();
        $m->delete();

        return response()->json(['message' => 'Member removed.']);
    }

    /** Assignable roles: system tenant roles (org NULL) + this org's roles — RLS already scopes them. */
    public function roles(): JsonResponse
    {
        $roles = Role::where('scope', 'tenant')->orderBy('name')->get();

        return response()->json([
            'data' => $roles->map(fn (Role $r) => ['id' => $r->id, 'name' => $r->name, 'description' => $r->description]),
        ]);
    }

    protected function resolveRoleIds(int $orgId, array $data): Collection
    {
        if (! empty($data['roles'])) {
            return Role::whereIn('id', $data['roles'])->where('scope', 'tenant')->pluck('id');
        }
        if (! empty($data['role'])) {
            return Role::whereNull('organization_id')->where('scope', 'tenant')->where('name', $data['role'])->pluck('id');
        }

        return collect();
    }

    protected function present(Membership $m): array
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
