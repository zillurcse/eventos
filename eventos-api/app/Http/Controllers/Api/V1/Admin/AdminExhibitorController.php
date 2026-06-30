<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Exhibitor;
use App\Models\ExhibitorMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Super-admin governance of exhibitors & sponsors across all tenants
 * (architecture §2.1, §6.3). Reads/writes run on the migrator (BYPASSRLS)
 * connection and drop the tenant global scope, since this control plane spans
 * every organization. Account-level actions (password reset, disable) on an
 * exhibitor-admin's login are handled through AdminUserController.
 */
class AdminExhibitorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $exhibitors = Exhibitor::on('pgsql_admin')
            ->withoutGlobalScope('organization')
            ->with(['organization:id,uuid,name', 'event:id,uuid,name', 'members' => fn ($q) => $q->with('contact')])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'ilike', '%'.trim((string) $request->query('q')).'%'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest('id')
            ->limit(200)
            ->get();

        return response()->json(['data' => $exhibitors->map(fn (Exhibitor $e) => $this->present($e))]);
    }

    public function show(string $uuid): JsonResponse
    {
        $exhibitor = Exhibitor::on('pgsql_admin')
            ->withoutGlobalScope('organization')
            ->with(['organization:id,uuid,name', 'event:id,uuid,name', 'members' => fn ($q) => $q->with('contact')])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json(['data' => array_merge($this->present($exhibitor), [
            'members' => $exhibitor->members->map(fn (ExhibitorMember $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'name' => $m->contact?->fullName(),
                'email' => $m->contact?->email,
                'has_login' => (bool) $m->contact?->user_id,
                'is_lead_capturer' => (bool) $m->is_lead_capturer,
            ])->values(),
        ])]);
    }

    /** Create or reset the exhibitor-admin login (User + linked Contact + admin member). */
    public function setAdmin(string $uuid, Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::on('pgsql_admin')->withoutGlobalScope('organization')->where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'email' => ['required', 'email'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        // Contact is unique by (organization_id, email); reuse or create on the exhibitor's org.
        $contact = Contact::on('pgsql_admin')->withoutGlobalScope('organization')
            ->where('organization_id', $exhibitor->organization_id)
            ->where('email', $data['email'])
            ->first();

        if (! $contact) {
            $contact = new Contact([
                'organization_id' => $exhibitor->organization_id,
                'email' => $data['email'],
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
            ]);
            $contact->setConnection('pgsql_admin');
            $contact->save();
        }

        // Create the login, or reset the password + re-enable if it already exists.
        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: $data['email'],
                'password' => $data['password'],
                'email_verified_at' => now(),
            ],
        );

        if (! $user->wasRecentlyCreated) {
            $user->forceFill(['password' => $data['password'], 'status' => 'active'])->save();  // status isn't fillable
        }

        $contact->update(['user_id' => $user->id]);

        // Ensure an admin exhibitor_member links the contact to this exhibitor.
        $member = ExhibitorMember::on('pgsql_admin')->withTrashed()
            ->where('exhibitor_id', $exhibitor->id)
            ->where('contact_id', $contact->id)
            ->first();

        if ($member) {
            if ($member->trashed()) {
                $member->restore();
            }
            $member->update(['role' => 'admin']);
        } else {
            $member = new ExhibitorMember(['exhibitor_id' => $exhibitor->id, 'contact_id' => $contact->id, 'role' => 'admin']);
            $member->setConnection('pgsql_admin');
            $member->save();
        }

        $exhibitor->update(['admin_contact_id' => $contact->id]);

        return response()->json([
            'data' => $this->present($exhibitor->fresh(['organization', 'event', 'members' => fn ($q) => $q->with('contact')])),
        ], 201);
    }

    /** Activate / suspend / draft an exhibitor. Suspending blocks its admin login (ResolveExhibitorAdmin). */
    public function update(string $uuid, Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::on('pgsql_admin')->withoutGlobalScope('organization')->where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'active', 'suspended'])],
        ]);

        $exhibitor->update(['status' => $data['status']]);

        return response()->json([
            'data' => $this->present($exhibitor->fresh(['organization', 'event', 'members' => fn ($q) => $q->with('contact')])),
        ]);
    }

    // ── helpers ──────────────────────────────────────────────

    protected function present(Exhibitor $e): array
    {
        $admin = $e->relationLoaded('members')
            ? $e->members->first(fn (ExhibitorMember $m) => $m->role === 'admin' && $m->contact?->user_id)
            : null;

        return [
            'id' => $e->uuid,
            'name' => $e->name,
            'type' => $e->type,                 // exhibitor | sponsor
            'status' => $e->status,             // draft | active | suspended
            'organization' => $e->organization?->name,
            'event' => $e->event?->name,
            'has_admin_login' => (bool) $admin,
            'admin_email' => $admin?->contact?->email,
        ];
    }
}
