<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Participation;
use App\Models\User;
use App\Services\Auth\EventAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Event admins (Settings › Add event admin) — "users added to the web app are
 * provided web app access".
 *
 * An event admin is a participation with role=staff. That is not a cosmetic
 * label: Session::isModeratedBy already treats staff as a session host, so these
 * people can moderate Q&A, run polls and take the stage without being on the
 * speaker line-up. They sign in to the *attendee* site (this is web-app access,
 * not admin-panel access — that is organization membership, a different thing).
 *
 * Adding one provisions a login if they have none and emails them a 6-digit
 * access code, mirroring the exhibitor-admin flow so there is one story for
 * "someone the organizer added can now sign in".
 */
class EventAdminController extends Controller
{
    public function __construct(private readonly EventAccess $access) {}

    public function index(string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $admins = Participation::with('contact')
            ->where('event_id', $event->id)
            ->where('role', 'staff')
            ->orderBy('id')
            ->get()
            ->map(fn (Participation $p) => $this->row($p));

        return response()->json(['data' => $admins]);
    }

    public function store(Request $request, string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:180'],
        ]);

        $email = Str::lower(trim($data['email']));

        // enrol() is idempotent and promotes an existing attendee to staff, so
        // adding someone who is already at the event does the right thing rather
        // than erroring or creating a second participation.
        [$user, $participation] = $this->access->enrol($event, $email, $data['name'] ?? null, staff: true);

        // Only mint an access code for someone who cannot already sign in — an
        // existing attendee keeps the password they have, and we must never mail
        // a fresh code to an account we did not just create.
        $code = null;
        if (! $user->password) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            User::on('pgsql_admin')->whereKey($user->id)->first()
                ?->forceFill(['password' => $code])->save();
        }

        $emailed = $code !== null && $this->mailAccessCode($email, $code, $event->name);

        return response()->json([
            'data' => $this->row($participation->fresh()->load('contact')),
            'meta' => [
                'invited' => $emailed,
                'had_login' => $code === null,
            ],
        ], 201);
    }

    /** Demote an event admin. Their participation stays — they remain an attendee. */
    public function destroy(string $uuid, string $participationUuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $participation = Participation::where('event_id', $event->id)
            ->where('uuid', $participationUuid)
            ->where('role', 'staff')
            ->firstOrFail();

        // Removing web-app *admin* rights should not evict them from the event
        // they may also be attending — drop the role, keep the participation.
        $participation->update(['role' => 'attendee']);

        return response()->json(['status' => 'success']);
    }

    private function row(Participation $p): array
    {
        $contact = $p->contact;

        return [
            'id' => $p->uuid,
            'name' => $contact?->fullName() ?: '—',
            'email' => $contact?->email,
            'has_login' => (bool) $contact?->user_id,
            'added_at' => $p->created_at?->toIso8601String(),
        ];
    }

    private function mailAccessCode(string $email, string $code, string $eventName): bool
    {
        $body = "Hello,\n\n"
            ."You've been given web app access to \"{$eventName}\".\n\n"
            ."Sign in with:\n"
            ."  Email:       {$email}\n"
            ."  Access code: {$code}\n\n"
            ."Use the access code as your password, then change it after signing in.\n";

        try {
            Mail::raw($body, fn ($m) => $m->to($email)->subject("Your access code for {$eventName}"));

            return true;
        } catch (\Throwable $e) {
            // A mail outage must not undo the access we just granted.
            report($e);

            return false;
        }
    }
}
