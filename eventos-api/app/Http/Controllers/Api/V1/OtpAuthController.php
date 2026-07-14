<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\EventAccess;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Sign in with a one-time code emailed to the attendee (Settings › Access
 * authentication › OTP).
 *
 * The code lives in the cache, not the database: it is worthless in ten minutes,
 * and a table of expired secrets is a liability with no upside. It is stored
 * *hashed* for the same reason we hash passwords — a cache dump should not hand
 * someone a working set of sign-in codes.
 *
 * Three abuse limits, because this endpoint mails strangers on demand:
 *   - per email: one code request every 60s, 5 an hour (no mail-bombing someone);
 *   - per IP: 20 requests an hour (no farming the endpoint for valid addresses);
 *   - per code: 5 wrong guesses and it dies (6 digits is 1-in-a-million, but
 *     only if you cannot sit there trying).
 *
 * The request endpoint always answers the same way whether or not the address is
 * known. Saying "no such account" would turn this into an attendee-list oracle
 * for anyone who wants to know who is at the event.
 */
class OtpAuthController extends Controller
{
    private const TTL_SECONDS = 600;          // 10 minutes

    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly EventAccess $access,
        private readonly TenantContext $tenant,
    ) {}

    /** POST /public/auth/otp — mail a fresh code. */
    public function request(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = Str::lower(trim($data['email']));

        [$event, $setting] = $this->event($request);

        abort_unless($this->access->channels($setting)['otp'], 403, 'Sign-in codes are not enabled for this event.');

        // Same answer either way — see the class docblock.
        $answer = response()->json([
            'sent' => true,
            'message' => 'If that email can sign in to this event, a code is on its way.',
            'expires_in' => self::TTL_SECONDS,
        ]);

        if (RateLimiter::tooManyAttempts($this->cooldownKey($email), 1)) {
            return $answer; // a code was sent seconds ago; don't send another
        }
        if (RateLimiter::tooManyAttempts($this->hourlyKey($email), 5)
            || RateLimiter::tooManyAttempts($this->ipKey($request), 20)) {
            return $answer;
        }

        RateLimiter::hit($this->cooldownKey($email), 60);
        RateLimiter::hit($this->hourlyKey($email), 3600);
        RateLimiter::hit($this->ipKey($request), 3600);

        // An unknown address gets no code — but the caller cannot tell, because
        // the response above is identical either way.
        if (! $this->canSignIn($email, $setting)) {
            return $answer;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->codeKey($event->id, $email), [
            'hash' => Hash::make($code),
            'attempts' => 0,
        ], self::TTL_SECONDS);

        $this->mail($email, $code, $event->name);

        return $answer;
    }

    /** POST /public/auth/otp/verify — trade a valid code for a token. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
        ]);

        $email = Str::lower(trim($data['email']));
        [$event, $setting] = $this->event($request);

        abort_unless($this->access->channels($setting)['otp'], 403, 'Sign-in codes are not enabled for this event.');

        $key = $this->codeKey($event->id, $email);
        $entry = Cache::get($key);

        if (! $entry) {
            return response()->json(['message' => 'That code has expired. Request a new one.'], 422);
        }

        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return response()->json(['message' => 'Too many attempts. Request a new code.'], 429);
        }

        if (! Hash::check(trim($data['code']), $entry['hash'])) {
            $entry['attempts']++;
            // Keep the remaining TTL rather than extending it: a wrong guess must
            // not buy the guesser another ten minutes.
            Cache::put($key, $entry, self::TTL_SECONDS);

            return response()->json(['message' => 'That code is not right.'], 422);
        }

        // Single use.
        Cache::forget($key);

        // Writes below are tenant rows — activate the event's org for RLS.
        $this->tenant->set($event->organization_id);
        DB::statement("set app.current_organization = '{$event->organization_id}'");

        [$user] = $this->access->enrol($event, $email);
        $this->access->assertEnabled($user);

        return response()->json([
            'token' => $this->access->token($user),
            'user' => new UserResource($user),
        ]);
    }

    /**
     * May this address sign in at all? An existing account always may. An unknown
     * one may only when the organizer left Signup on — otherwise OTP would be a
     * back door around a closed event.
     */
    private function canSignIn(string $email, $setting): bool
    {
        $exists = User::on('pgsql_admin')->where('email', $email)->exists();

        return $exists || $this->access->channels($setting)['signup'];
    }

    private function event(Request $request): array
    {
        $resolved = $this->access->resolve($request->header('X-Event-Subdomain'));

        abort_if($resolved === null, 404, 'Event not found.');

        return $resolved;
    }

    private function mail(string $email, string $code, string $eventName): void
    {
        $body = "Your sign-in code for {$eventName} is:\n\n    {$code}\n\n"
            ."It expires in 10 minutes and can be used once.\n\n"
            ."If you didn't ask to sign in, you can ignore this email.\n";

        try {
            Mail::raw($body, fn ($m) => $m->to($email)->subject("Your {$eventName} sign-in code: {$code}"));
        } catch (\Throwable $e) {
            // A mail outage must not become a 500 that tells the caller the
            // address exists. Log it; the attendee simply never gets the code.
            report($e);
        }
    }

    private function codeKey(int $eventId, string $email): string
    {
        return 'otp:'.$eventId.':'.sha1($email);
    }

    private function cooldownKey(string $email): string
    {
        return 'otp-cooldown:'.sha1($email);
    }

    private function hourlyKey(string $email): string
    {
        return 'otp-hourly:'.sha1($email);
    }

    private function ipKey(Request $request): string
    {
        return 'otp-ip:'.sha1((string) $request->ip());
    }
}
