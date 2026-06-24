<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Contact;
use App\Models\Membership;
use App\Models\PartnerMember;
use App\Models\User;
use App\Services\Tenancy\OrganizationProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, OrganizationProvisioner $provisioner): JsonResponse
    {
        $data = $request->validated();

        $result = $provisioner->register(
            $data['name'], $data['email'], $data['password'], $data['organization_name'],
        );

        /** @var User $user */
        $user = $result['user'];
        $token = $user->createToken('api', ['tenant'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($this->withIdentity($user)),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['These credentials do not match our records.']]);
        }

        // Disabled accounts cannot obtain a token (super-admin can re-enable).
        if ($user->status === 'disabled') {
            throw ValidationException::withMessages(['email' => ['This account has been disabled.']]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $ability = $user->isPlatformStaff() ? 'platform' : 'tenant';
        $token = $user->createToken('api', [$ability])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($this->withIdentity($user)),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($this->withIdentity($request->user())),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Attach the user's cross-org memberships AND partner memberships. This is an
     * identity-plane lookup (no tenant chosen yet), so it runs on the migrator
     * connection (BYPASSRLS) but is constrained to the user's own rows. The SPA
     * uses these to classify the signed-in persona (platform/organizer/partner).
     */
    protected function withIdentity(User $user): User
    {
        $memberships = Membership::on('pgsql_admin')
            ->with(['organization', 'roles'])
            ->where('user_id', $user->id)
            ->get();

        $contactIds = Contact::on('pgsql_admin')->where('user_id', $user->id)->pluck('id');

        $partnerMemberships = PartnerMember::on('pgsql_admin')
            ->with(['partner.organization', 'partner.event'])
            ->whereIn('contact_id', $contactIds)
            ->get();

        return $user
            ->setRelation('memberships', $memberships)
            ->setRelation('partnerMemberships', $partnerMemberships);
    }
}
