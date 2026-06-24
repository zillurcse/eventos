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

/**
 * Partner staff/members. An `admin` member with a password gets a login User
 * (linked to their contact) so they can self-manage the partner space (§6.3).
 */
class PartnerMemberController extends Controller
{
    public function store(string $partnerUuid, Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:admin,staff'],
            'is_lead_capturer' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $partner = Partner::where('uuid', $partnerUuid)->firstOrFail();

        $contact = Contact::firstOrCreate(
            ['email' => $data['email']],
            ['first_name' => $data['first_name'] ?? null, 'last_name' => $data['last_name'] ?? null],
        );

        // Any member given a password gets a login (admins and staff both
        // self-serve the partner space — architecture §6.3).
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

    public function destroy(string $partnerUuid, int $member): JsonResponse
    {
        $partner = Partner::where('uuid', $partnerUuid)->firstOrFail();

        PartnerMember::where('partner_id', $partner->id)->where('id', $member)->firstOrFail()->delete();

        return response()->json(['message' => 'Member removed.']);
    }
}
