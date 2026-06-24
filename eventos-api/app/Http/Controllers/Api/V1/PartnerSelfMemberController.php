<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartnerMemberResource;
use App\Models\Contact;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Partner team self-management (architecture §6.3). The active partner is
 * resolved by ResolvePartnerAdmin, which sets the tenant GUC to that partner's
 * org — so org-scoped writes (contacts, partner_members) satisfy RLS on the
 * default connection. Member logins are global `users` (no RLS).
 */
class PartnerSelfMemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $members = PartnerMember::with('contact')
            ->where('partner_id', $request->attributes->get('partner_id'))
            ->latest('id')
            ->get();

        return response()->json(['data' => PartnerMemberResource::collection($members)]);
    }

    /** Invite a teammate; supplying a password gives them a login (admin or staff). */
    public function store(Request $request): JsonResponse
    {
        $partner = Partner::findOrFail($request->attributes->get('partner_id'));

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

        $member = PartnerMember::updateOrCreate(
            ['partner_id' => $partner->id, 'contact_id' => $contact->id],
            ['role' => $data['role'] ?? 'staff', 'is_lead_capturer' => $data['is_lead_capturer'] ?? false],
        );

        return response()->json(['data' => new PartnerMemberResource($member->load('contact'))], 201);
    }

    public function destroy(Request $request, int $member): JsonResponse
    {
        PartnerMember::where('partner_id', $request->attributes->get('partner_id'))
            ->where('id', $member)
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'Member removed.']);
    }

    /** Reset a teammate's login password. */
    public function password(Request $request, int $member): JsonResponse
    {
        $m = PartnerMember::with('contact')
            ->where('partner_id', $request->attributes->get('partner_id'))
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
