<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Contact;
use App\Models\Membership;
use App\Models\ExhibitorMember;
use App\Models\User;
use App\Services\Tenancy\OrganizationProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

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
        $newToken = $user->createToken('api', ['tenant']);
        $this->rememberDevice($newToken->accessToken, $request);

        return response()->json([
            'token' => $newToken->plainTextToken,
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
        $newToken = $user->createToken('api', [$ability]);
        $this->rememberDevice($newToken->accessToken, $request);

        return response()->json([
            'token' => $newToken->plainTextToken,
            'user' => new UserResource($this->withIdentity($user)),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        // Hit on every app boot from every signed-in device, so this is what
        // actually self-heals "Unknown Device" for tokens minted before device
        // tracking existed — each real device fills its own token in as soon
        // as it's next used, without needing a blanket middleware.
        $this->backfillCurrentDevice($request);

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
     * POST /auth/change-password — the signed-in user's own password, from
     * Profile › Account Settings (any persona: admin, exhibitor or event
     * attendee all share the one `users` login). Requires the current
     * password; an account with none yet (OTP/social-only) can't use this
     * path — that's a "set a password" flow, not a "change" one.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! $user->password || ! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Current password is incorrect.']]);
        }

        $user->forceFill(['password' => $data['password']])->save();   // hashed by cast

        return response()->json(['message' => 'Password updated.']);
    }

    /**
     * GET /auth/sessions — every active login token for the signed-in user
     * (Profile › Account Settings › Browser Session), newest activity first.
     * A "session" here is a Sanctum personal access token, not a Laravel
     * cookie session — this app is API-token auth end to end.
     */
    public function sessions(Request $request): JsonResponse
    {
        $this->backfillCurrentDevice($request);
        $currentId = $request->user()->currentAccessToken()?->id;

        $tokens = $request->user()->tokens()
            ->orderByRaw('COALESCE(last_used_at, created_at) DESC')
            ->get();

        return response()->json([
            'data' => $tokens->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'device' => $this->deviceName($token->user_agent),
                'ip_address' => $token->ip_address,
                'last_active_at' => ($token->last_used_at ?? $token->created_at)?->toIso8601String(),
                'is_current' => $token->id === $currentId,
            ]),
        ]);
    }

    /** DELETE /auth/sessions/{id} — log out one other browser/device. Can't revoke the token making this request. */
    public function revokeSession(Request $request, int $id): JsonResponse
    {
        $currentId = $request->user()->currentAccessToken()?->id;

        if ($id === $currentId) {
            throw ValidationException::withMessages(['id' => ['Use logout to end your current session.']]);
        }

        $request->user()->tokens()->where('id', $id)->delete();

        return response()->json(['message' => 'Session logged out.']);
    }

    /** POST /auth/sessions/logout-others — end every session except the one making this request. */
    public function logoutOtherSessions(Request $request): JsonResponse
    {
        $currentId = $request->user()->currentAccessToken()?->id;

        $request->user()->tokens()->where('id', '!=', $currentId)->delete();

        return response()->json(['message' => 'Logged out of all other sessions.']);
    }

    /** Best-effort "Chrome (Windows)" style label from the login request's User-Agent string. */
    private function deviceName(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Unknown Device';
        }

        $os = match (true) {
            (bool) preg_match('/iPhone|iPad/i', $userAgent) => 'iOS',
            (bool) preg_match('/Android/i', $userAgent) => 'Android',
            (bool) preg_match('/Mac OS X/i', $userAgent) => 'macOS',
            (bool) preg_match('/Windows/i', $userAgent) => 'Windows',
            (bool) preg_match('/Linux/i', $userAgent) => 'Linux',
            default => null,
        };

        $browser = match (true) {
            (bool) preg_match('/Edg\//i', $userAgent) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $userAgent) => 'Opera',
            (bool) preg_match('/Chrome\//i', $userAgent) => 'Chrome',
            (bool) preg_match('/CriOS/i', $userAgent) => 'Chrome',
            (bool) preg_match('/FxiOS|Firefox/i', $userAgent) => 'Firefox',
            (bool) preg_match('/Safari\//i', $userAgent) => 'Safari',
            default => null,
        };

        if ($browser && $os) {
            return "{$browser} ({$os})";
        }

        return $browser ?? $os ?? 'Unknown Device';
    }

    /**
     * Tokens minted before device tracking existed (or via a path that predates
     * it) have no ip/user_agent and never will on their own — we only stamp at
     * login. The token making *this* request is the one exception: we know its
     * device right now, so backfill it lazily rather than leaving it as
     * "Unknown Device" forever.
     */
    private function backfillCurrentDevice(Request $request): void
    {
        $current = $request->user()->currentAccessToken();

        if ($current instanceof PersonalAccessToken && ! $current->user_agent) {
            $this->rememberDevice($current, $request);
        }
    }

    /** Stamp a freshly minted token with where it came from, for the sessions list above. */
    private function rememberDevice(PersonalAccessToken $token, Request $request): void
    {
        $token->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ])->save();
    }

    /**
     * Attach the user's cross-org memberships AND exhibitor memberships. This is an
     * identity-plane lookup (no tenant chosen yet), so it runs on the migrator
     * connection (BYPASSRLS) but is constrained to the user's own rows. The SPA
     * uses these to classify the signed-in persona (platform/organizer/exhibitor).
     */
    protected function withIdentity(User $user): User
    {
        $memberships = Membership::on('pgsql_admin')
            ->with(['organization', 'roles'])
            ->where('user_id', $user->id)
            ->get();

        $contactIds = Contact::on('pgsql_admin')->where('user_id', $user->id)->pluck('id');

        $exhibitorMemberships = ExhibitorMember::on('pgsql_admin')
            ->with(['exhibitor.organization', 'exhibitor.event'])
            ->whereIn('contact_id', $contactIds)
            ->get();

        return $user
            ->setRelation('memberships', $memberships)
            ->setRelation('exhibitorMemberships', $exhibitorMemberships);
    }
}
