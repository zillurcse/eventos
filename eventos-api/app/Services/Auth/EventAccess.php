<?php

namespace App\Services\Auth;

use App\Models\Contact;
use App\Models\Event;
use App\Models\EventSetting;
use App\Models\Participation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Everything the attendee sign-in paths share (Settings › Access authentication).
 *
 * Three doors lead into an event site — a password, an emailed OTP, and a social
 * account — and all three end in the same place: a `users` row to authenticate,
 * a `contacts` row to hang identity off, and a `participations` row that makes
 * this person a participant *of this event*. Miss the last one and they sign in
 * successfully to a site where every tab 403s (ResolveParticipant), which is a
 * uniquely confusing kind of broken. So the enrolment lives here, once, rather
 * than three times.
 *
 * Which doors are open is the organizer's call (event_settings.login), but a
 * channel is only ever *offered* when the platform can actually honour it — see
 * channels(): ticking "Google" on an installation with no Google OAuth app
 * configured must not put a button on the login page that dead-ends.
 */
class EventAccess
{
    /** The social providers we know how to talk to, and their Socialite driver. */
    public const SOCIAL_DRIVERS = [
        'google' => 'google',
        'facebook' => 'facebook',
        'linkedin' => 'linkedin-openid',
    ];

    /** Resolve a published event from its subdomain, with its settings. */
    public function resolve(?string $subdomain): ?array
    {
        if (! $subdomain) {
            return null;
        }

        $setting = EventSetting::on('pgsql_admin')
            ->where('domain->subdomain', $subdomain)
            ->first();

        if (! $setting) {
            return null;
        }

        $event = Event::on('pgsql_admin')->find($setting->event_id);

        // Draft events are private: they cannot be signed in to either.
        if (! $event || $event->status !== 'published') {
            return null;
        }

        return [$event, $setting];
    }

    /**
     * The sign-in channels this event actually offers.
     *
     * `signup` and `otp` we implement ourselves, so the organizer's toggle is the
     * whole story. A social channel additionally needs an OAuth app for that
     * provider — either the organizer's own (Settings › Access authentication)
     * or the platform's — without a client id there is nothing to redirect to,
     * so we report it unavailable no matter what the organizer ticked. Password
     * sign-in is not a channel — it is always available to someone who already
     * has an account.
     */
    public function channels(?EventSetting $setting): array
    {
        $login = $setting?->login ?? [];
        $methods = $login['methods'] ?? [];

        $channels = [
            // Default on: an event nobody has configured must still let people in.
            'signup' => ($methods['signup'] ?? true) !== false,
            'otp' => (bool) ($methods['otp'] ?? false),
        ];

        foreach (array_keys(self::SOCIAL_DRIVERS) as $provider) {
            $channels[$provider] = (bool) ($methods[$provider] ?? false)
                && $this->providerConfigured($provider, $setting);
        }

        return $channels;
    }

    /** Does an OAuth app exist for this provider — the organizer's own, or the platform's? */
    public function providerConfigured(string $provider, ?EventSetting $setting = null): bool
    {
        return self::SOCIAL_DRIVERS[$provider] ?? null
            ? filled($this->credentials($provider, $setting)['client_id'])
            : false;
    }

    /**
     * The OAuth client id/secret to use for this provider on this event.
     *
     * An organizer who has registered their own app (with our fixed callback URL
     * whitelisted in it) gets that; otherwise we fall back to the platform's app,
     * if one is configured. The redirect URI is always ours — see
     * SocialAuthController — because it is what every OAuth app's console has
     * been told to allow, regardless of whose client id/secret is behind it.
     */
    public function credentials(string $provider, ?EventSetting $setting = null): array
    {
        $driver = self::SOCIAL_DRIVERS[$provider] ?? null;
        $own = $setting?->login['social_credentials'][$provider] ?? [];

        $clientId = filled($own['client_id'] ?? null) ? $own['client_id'] : config("services.{$driver}.client_id");
        $clientSecret = filled($own['client_id'] ?? null) && filled($own['client_secret'] ?? null)
            ? $own['client_secret']
            : config("services.{$driver}.client_secret");

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect' => config("services.{$driver}.redirect"),
        ];
    }

    /**
     * Make sure this person exists, can sign in, and participates in this event.
     *
     * Idempotent: the same email arriving by password today, Google tomorrow and
     * an OTP next week is one user, one contact and one participation. Identity
     * (users) is global; contacts and participations are tenant rows, so they are
     * written under the event's organization.
     *
     * @param  bool  $staff  event admins get role=staff, which is what
     *                       Session::isModeratedBy reads to grant moderator powers
     */
    public function enrol(Event $event, string $email, ?string $name = null, bool $staff = false): array
    {
        $email = Str::lower(trim($email));

        return DB::transaction(function () use ($event, $email, $name, $staff) {
            // Identity is not tenant-scoped — it lives on the admin connection.
            $user = User::on('pgsql_admin')->where('email', $email)->first();

            if (! $user) {
                $user = (new User)->setConnection('pgsql_admin');
                $user->forceFill([
                    'name' => $name ?: Str::before($email, '@'),
                    'email' => $email,
                    // No password: they arrived by OTP or social. A password can be
                    // set later; until then those doors are the only way in.
                    'password' => null,
                    'email_verified_at' => now(),
                ])->save();
            }

            $contact = Contact::firstOrCreate(
                ['email' => $email],
                ['first_name' => Str::before($name ?: $email, ' '), 'last_name' => Str::after($name ?: '', ' ')],
            );

            if ($contact->user_id !== $user->id) {
                $contact->update(['user_id' => $user->id]);
            }

            $participation = Participation::where('event_id', $event->id)
                ->where('contact_id', $contact->id)
                ->first();

            if (! $participation) {
                $participation = Participation::create([
                    'event_id' => $event->id,
                    'contact_id' => $contact->id,
                    'role' => $staff ? 'staff' : 'attendee',
                    'status' => 'confirmed',
                ]);
            } elseif ($staff && $participation->role !== 'staff') {
                // Promoting an existing attendee to event admin.
                $participation->update(['role' => 'staff']);
            }

            return [$user, $participation];
        });
    }

    /** A Sanctum token for the attendee site. */
    public function token(User $user): string
    {
        $user->forceFill(['last_login_at' => now()])->save();

        return $user->createToken('event', ['tenant'])->plainTextToken;
    }

    /** Disabled accounts cannot get in through any door. */
    public function assertEnabled(User $user): void
    {
        abort_if($user->status === 'disabled', 403, 'This account has been disabled.');
    }
}
