<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExhibitorMemberResource;
use App\Models\Contact;
use App\Models\Exhibitor;
use App\Models\ExhibitorMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Exhibitor team self-management (architecture §6.3). The active exhibitor is
 * resolved by ResolveExhibitorAdmin, which sets the tenant GUC to that exhibitor's
 * org — so org-scoped writes (contacts, exhibitor_members) satisfy RLS on the
 * default connection. Member logins are global `users` (no RLS).
 */
class ExhibitorSelfMemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $members = ExhibitorMember::with('contact')
            ->where('exhibitor_id', $request->attributes->get('exhibitor_id'))
            ->latest('id')
            ->get();

        return response()->json(['data' => ExhibitorMemberResource::collection($members)]);
    }

    /** Invite a teammate; supplying a password gives them a login (admin or staff). */
    public function store(Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::findOrFail($request->attributes->get('exhibitor_id'));

        $data = $request->validate([
            'email' => ['required', 'email'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(['admin', 'staff'])],
            'is_lead_capturer' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $contact = Contact::firstOrCreate(
            ['email' => $data['email']],
            ['first_name' => $data['first_name'] ?? null, 'last_name' => $data['last_name'] ?? null],
        );

        if (! empty($data['password']) && ! $contact->user_id) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: $data['email'],
                    'password' => $data['password'],
                    'email_verified_at' => now(),
                ],
            );
            $contact->update(['user_id' => $user->id]);
        }

        $member = ExhibitorMember::updateOrCreate(
            ['exhibitor_id' => $exhibitor->id, 'contact_id' => $contact->id],
            ['role' => $data['role'] ?? 'staff', 'is_lead_capturer' => $data['is_lead_capturer'] ?? false],
        );

        return response()->json(['data' => new ExhibitorMemberResource($member->load('contact'))], 201);
    }

    /** Modules a staff member can be granted access to (the team ACL). */
    public const MODULES = ['products', 'documents', 'projects', 'leads', 'meetings'];

    /** Update a member's role + per-module access (ACL). Admins get everything. */
    public function update(Request $request, int $member): JsonResponse
    {
        $m = ExhibitorMember::with('contact')
            ->where('exhibitor_id', $request->attributes->get('exhibitor_id'))
            ->where('id', $member)
            ->firstOrFail();

        $data = $request->validate([
            'role' => ['sometimes', Rule::in(['admin', 'staff'])],
            'is_lead_capturer' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['boolean'],
        ]);

        $update = [];
        if (array_key_exists('role', $data)) {
            $update['role'] = $data['role'];
        }
        if (array_key_exists('is_lead_capturer', $data)) {
            $update['is_lead_capturer'] = $data['is_lead_capturer'];
        }
        if (array_key_exists('permissions', $data)) {
            // Keep only known modules.
            $update['permissions'] = collect(self::MODULES)
                ->mapWithKeys(fn ($k) => [$k => (bool) ($data['permissions'][$k] ?? false)])
                ->all();
        }

        $m->update($update);

        return response()->json(['data' => new ExhibitorMemberResource($m->fresh('contact'))]);
    }

    public function destroy(Request $request, int $member): JsonResponse
    {
        ExhibitorMember::where('exhibitor_id', $request->attributes->get('exhibitor_id'))
            ->where('id', $member)
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'Member removed.']);
    }

    /** Reset a teammate's login password. */
    public function password(Request $request, int $member): JsonResponse
    {
        $m = ExhibitorMember::with('contact')
            ->where('exhibitor_id', $request->attributes->get('exhibitor_id'))
            ->where('id', $member)
            ->firstOrFail();

        $data = $request->validate(['password' => ['required', 'string', 'min:8']]);

        abort_unless($m->contact && $m->contact->user_id, 422, 'This member has no login.');

        User::whereKey($m->contact->user_id)->firstOrFail()
            ->forceFill(['password' => $data['password'], 'status' => 'active'])   // status not fillable
            ->save();

        return response()->json(['message' => 'Password updated.']);
    }
}
