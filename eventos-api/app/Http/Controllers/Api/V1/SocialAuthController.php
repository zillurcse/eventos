<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EventSetting;
use App\Models\User;
use App\Services\Auth\EventAccess;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Social sign-in for attendee sites (Settings › Access authentication).
 *
 * ── Why one callback for every event ────────────────────────────────────────
 * Providers verify a fixed redirect URI per OAuth app, so a per-event callback
 * (`https://<sub>.expouse.test/callback`) would mean registering a Google app
 * per event — unusable. Instead the platform holds one app per provider, the
 * callback is always this API, and *which event* the person is signing in to
 * rides through the OAuth `state` parameter, encrypted.
 *
 * `state` is not decoration here: it is the CSRF defence for the whole flow, and
 * it is why we can trust the event on the way back. We encrypt it (so nobody can
 * forge one pointing at an event they were never sent to) and we drive Socialite
 * `stateless()` because an API has no session to keep it in.
 *
 * ── Getting the token home ──────────────────────────────────────────────────
 * The attendee ends up back on their event's site with a Sanctum token. It goes
 * in the URL *fragment*, not the query string: fragments are not sent to servers
 * and do not land in access logs, proxy logs or the Referer header. The SPA
 * reads it and immediately strips it from the address bar.
 *
 * The return URL is not taken from the request — that would be an open redirect
 * handing tokens to whatever host an attacker names. It is derived from the
 * event's own subdomain.
 */
class SocialAuthController extends Controller
{
    public function __construct(
        private readonly EventAccess $access,
        private readonly TenantContext $tenant,
    ) {}

    /** GET /auth/social/{provider}/redirect?subdomain=… — send them to the provider. */
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $driver = EventAccess::SOCIAL_DRIVERS[$provider] ?? null;
        abort_if($driver === null, 404, 'Unknown sign-in provider.');

        $subdomain = (string) $request->query('subdomain', '');
        $resolved = $this->access->resolve($subdomain);
        abort_if($resolved === null, 404, 'Event not found.');

        [, $setting] = $resolved;

        // Re-checked here, not just in the UI: a hand-crafted link must not open
        // a door the organizer closed (or one the platform cannot honour).
        abort_unless(
            $this->access->channels($setting)[$provider] ?? false,
            403,
            'That sign-in method is not available for this event.',
        );

        $state = Crypt::encryptString(json_encode([
            'subdomain' => $subdomain,
            'provider' => $provider,
            'issued_at' => now()->timestamp,
        ]));

        $this->useEventCredentials($provider, $driver, $setting);

        return Socialite::driver($driver)
            ->stateless()
            ->with(['state' => $state])
            ->redirect();
    }

    /** GET /auth/social/{provider}/callback — the provider sends them back here. */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $driver = EventAccess::SOCIAL_DRIVERS[$provider] ?? null;
        abort_if($driver === null, 404, 'Unknown sign-in provider.');

        $state = $this->readState($request, $provider);
        $resolved = $this->access->resolve($state['subdomain']);
        abort_if($resolved === null, 404, 'Event not found.');

        [$event, $setting] = $resolved;

        abort_unless(
            $this->access->channels($setting)[$provider] ?? false,
            403,
            'That sign-in method is not available for this event.',
        );

        $this->useEventCredentials($provider, $driver, $setting);

        try {
            $social = Socialite::driver($driver)->stateless()->user();
        } catch (\Throwable $e) {
            report($e);

            return $this->back($state['subdomain'], ['error' => 'social_failed']);
        }

        $email = Str::lower(trim((string) $social->getEmail()));

        // Facebook will happily return a user with no email (the person signed up
        // by phone, or declined the scope). We have nothing to key identity on,
        // so send them back to sign in the ordinary way rather than inventing an
        // account that can never be matched to their registration.
        if ($email === '') {
            return $this->back($state['subdomain'], ['error' => 'social_no_email']);
        }

        // An unknown person may only be created if the organizer left Signup on.
        if (! $this->access->channels($setting)['signup'] && ! $this->knownUser($email)) {
            return $this->back($state['subdomain'], ['error' => 'signup_closed']);
        }

        $this->tenant->set($event->organization_id);
        DB::statement("set app.current_organization = '{$event->organization_id}'");

        [$user] = $this->access->enrol($event, $email, $social->getName());

        if ($user->status === 'disabled') {
            return $this->back($state['subdomain'], ['error' => 'account_disabled']);
        }

        return $this->back($state['subdomain'], [], $this->access->token($user));
    }

    /**
     * Point Socialite at this event's OAuth app for the rest of the request.
     *
     * An organizer may have registered their own app (Settings › Access
     * authentication); otherwise this is a no-op and the platform's app
     * (config/services.php) is what Socialite already has loaded. Scoped to a
     * single request/driver call — never call this twice for the same $driver
     * with different credentials, since Socialite\Manager caches driver
     * instances by name and the second call would reuse the first's config.
     */
    private function useEventCredentials(string $provider, string $driver, ?EventSetting $setting): void
    {
        $creds = $this->access->credentials($provider, $setting);

        config(["services.{$driver}.client_id" => $creds['client_id']]);
        config(["services.{$driver}.client_secret" => $creds['client_secret']]);
    }

    /** Decrypt and sanity-check the state we minted on the way out. */
    private function readState(Request $request, string $provider): array
    {
        try {
            $state = json_decode(Crypt::decryptString((string) $request->query('state')), true);
        } catch (\Throwable) {
            abort(400, 'Invalid sign-in state.');
        }

        abort_unless(
            is_array($state) && ($state['provider'] ?? null) === $provider && ! empty($state['subdomain']),
            400,
            'Invalid sign-in state.',
        );

        // A round trip through a provider takes seconds; an hour-old state is
        // someone replaying a link they found.
        abort_if(now()->timestamp - (int) ($state['issued_at'] ?? 0) > 3600, 400, 'This sign-in link has expired.');

        return $state;
    }

    private function knownUser(string $email): bool
    {
        return User::on('pgsql_admin')->where('email', $email)->exists();
    }

    /**
     * Back to the event site. The token rides in the fragment so it never reaches
     * a server log; errors ride in the query string because they are not secret
     * and the SPA may need them after a reload.
     */
    private function back(string $subdomain, array $query = [], ?string $token = null): RedirectResponse
    {
        $base = rtrim((string) config('app.event_site_url', env('EVENT_SITE_URL', 'http://localhost:3001')), '/');
        $host = parse_url($base, PHP_URL_HOST) ?: 'localhost';

        // Dev runs every event on one host, so the subdomain travels as a query
        // param (matching the SPA's own useEventSubdomain fallback); production
        // gives each event a real subdomain.
        $url = str_contains($host, 'localhost') || filter_var($host, FILTER_VALIDATE_IP)
            ? $base.'/?subdomain='.urlencode($subdomain)
            : preg_replace('#^(https?://)#', '$1'.$subdomain.'.', $base).'/';

        foreach ($query as $key => $value) {
            $url .= (str_contains($url, '?') ? '&' : '?').$key.'='.urlencode((string) $value);
        }

        if ($token !== null) {
            $url .= '#token='.urlencode($token);
        }

        return redirect()->away($url);
    }
}
