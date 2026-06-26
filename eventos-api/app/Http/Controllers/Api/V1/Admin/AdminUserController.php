<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Membership;
use App\Models\ExhibitorMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Super-admin account governance (architecture §2.1). Manages every login
 * account on the platform — platform staff, organizer members, and exhibitor
 * admins — across all tenants. Org-scoped lookups (memberships, contacts,
 * exhibitor members) run on the migrator (BYPASSRLS) connection; the global
 * `users` table has no RLS, so it uses the default connection.
 */
class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Classification id-sets — drive both the `types` label and the ?type= filter.
        $organizerIds = Membership::on('pgsql_admin')->distinct()->pluck('user_id');
        $exhibitorUserIds = $this->exhibitorAdminUserIds();
        $type = $request->query('type');

        $users = User::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.trim((string) $request->query('q')).'%';
                $q->where(fn ($w) => $w->where('name', 'ilike', $term)->orWhere('email', 'ilike', $term));
            })
            ->when($type === 'platform', fn ($q) => $q->where('is_platform_staff', true))
            ->when($type === 'organizer', fn ($q) => $q->whereIn('id', $organizerIds))
            ->when($type === 'exhibitor', fn ($q) => $q->whereIn('id', $exhibitorUserIds))
            ->latest('id')
            ->limit(100)
            ->get();

        $memberships = Membership::on('pgsql_admin')
            ->with(['organization:id,uuid,name', 'roles:id,name'])
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->groupBy('user_id');

        return response()->json([
            'data' => $users->map(fn (User $u) => $this->present(
                $u, $memberships->get($u->id) ?? collect(), $organizerIds, $exhibitorUserIds,
            )),
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        $user = User::where('uuid', $uuid)->firstOrFail();

        return response()->json(['data' => $this->detail($user)]);
    }

    /** Create a platform super-admin (platform access is the boolean flag, not a membership). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'string', 'min:8'],
            'is_platform_staff' => ['sometimes', 'boolean'],
        ]);

        // forceFill: is_platform_staff / status / email_verified_at are not in the
        // User model's $fillable allow-list, so mass assignment would drop them.
        $user = (new User)->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],                 // hashed by cast
            'is_platform_staff' => $data['is_platform_staff'] ?? true,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->save();

        return response()->json(['data' => $this->detail($user)], 201);
    }

    public function update(string $uuid, Request $request): JsonResponse
    {
        $user = User::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)->whereNull('deleted_at')],
            'is_platform_staff' => ['sometimes', 'boolean'],
        ]);

        // Self-guard: never let an admin strip their own platform access.
        if (array_key_exists('is_platform_staff', $data) && ! $data['is_platform_staff'] && $user->id === $request->user()->id) {
            abort(422, 'You cannot remove your own platform access.');
        }

        $user->forceFill($data)->save();                     // is_platform_staff isn't fillable

        return response()->json(['data' => $this->detail($user->refresh())]);
    }

    /** Admin-set a new password (does not revoke existing sessions). */
    public function password(string $uuid, Request $request): JsonResponse
    {
        $user = User::where('uuid', $uuid)->firstOrFail();
        $data = $request->validate(['password' => ['required', 'string', 'min:8']]);

        $user->forceFill(['password' => $data['password']])->save();   // hashed by cast

        return response()->json(['message' => 'Password updated.']);
    }

    /** Enable/disable an account. Disabling revokes all tokens for an immediate logout. */
    public function setStatus(string $uuid, Request $request): JsonResponse
    {
        $user = User::where('uuid', $uuid)->firstOrFail();
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'disabled'])]]);

        if ($data['status'] === 'disabled' && $user->id === $request->user()->id) {
            abort(422, 'You cannot disable your own account.');
        }

        $user->forceFill(['status' => $data['status']])->save();   // status isn't fillable

        if ($data['status'] === 'disabled') {
            $user->tokens()->delete();
        }

        return response()->json(['data' => $this->detail($user->refresh())]);
    }

    public function destroy(string $uuid, Request $request): JsonResponse
    {
        $user = User::where('uuid', $uuid)->firstOrFail();

        if ($user->id === $request->user()->id) {
            abort(422, 'You cannot delete your own account.');
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account removed.']);
    }

    // ── helpers ──────────────────────────────────────────────

    /** Users that hold an admin login for at least one exhibitor. */
    protected function exhibitorAdminUserIds(): Collection
    {
        $adminContactIds = ExhibitorMember::on('pgsql_admin')->where('role', 'admin')->pluck('contact_id');

        return Contact::on('pgsql_admin')
            ->whereIn('id', $adminContactIds)
            ->whereNotNull('user_id')
            ->pluck('user_id');
    }

    /** Load one user's memberships + classification for show/store/update/setStatus responses. */
    protected function detail(User $user): array
    {
        $memberships = Membership::on('pgsql_admin')
            ->with(['organization:id,uuid,name', 'roles:id,name'])
            ->where('user_id', $user->id)
            ->get();

        $organizerIds = $memberships->isNotEmpty() ? collect([$user->id]) : collect();

        return $this->present($user, $memberships, $organizerIds, $this->exhibitorAdminUserIds());
    }

    protected function present(User $u, Collection $memberships, Collection $organizerIds, Collection $exhibitorUserIds): array
    {
        $types = [];
        if ($u->is_platform_staff) {
            $types[] = 'platform';
        }
        if ($organizerIds->contains($u->id)) {
            $types[] = 'organizer';
        }
        if ($exhibitorUserIds->contains($u->id)) {
            $types[] = 'exhibitor';
        }

        return [
            'id' => $u->uuid,
            'name' => $u->name,
            'email' => $u->email,
            'is_platform_staff' => (bool) $u->is_platform_staff,
            'status' => $u->status,
            'last_login_at' => $u->last_login_at?->toIso8601String(),
            'types' => $types ?: ['user'],
            'memberships' => $memberships->map(fn (Membership $m) => [
                'id' => $m->id,
                'organization' => $m->organization?->name,
                'organization_id' => $m->organization?->uuid,
                'status' => $m->status,
                'roles' => $m->roles->pluck('name')->values(),
            ])->values(),
        ];
    }
}
