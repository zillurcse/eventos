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

/**
 * Exhibitor staff/members. An `admin` member with a password gets a login User
 * (linked to their contact) so they can self-manage the exhibitor space (§6.3).
 */
class ExhibitorMemberController extends Controller
{
    public function store(string $exhibitorUuid, Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:admin,staff'],
            'is_lead_capturer' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $exhibitor = Exhibitor::where('uuid', $exhibitorUuid)->firstOrFail();

        $contact = Contact::firstOrCreate(
            ['email' => $data['email']],
            ['first_name' => $data['first_name'] ?? null, 'last_name' => $data['last_name'] ?? null],
        );

        // Any member given a password gets a login (admins and staff both
        // self-serve the exhibitor space — architecture §6.3).
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

    public function destroy(string $exhibitorUuid, int $member): JsonResponse
    {
        $exhibitor = Exhibitor::where('uuid', $exhibitorUuid)->firstOrFail();

        ExhibitorMember::where('exhibitor_id', $exhibitor->id)->where('id', $member)->firstOrFail()->delete();

        return response()->json(['message' => 'Member removed.']);
    }
}
