<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExhibitorResource;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Exhibitor;
use App\Models\ExhibitorMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Unified exhibitor | sponsor management (architecture §6.3). Organizer-side.
 *
 * Core attributes live in real columns; the long tail of builder/UI fields
 * (address, social, cta, spotlight, flags, contact, feature entitlements) is
 * kept in the profile_data JSONB and merged on update so each drawer tab can
 * save independently.
 */
class ExhibitorController extends Controller
{
    /** Builder/UI fields stored in profile_data (not real columns). */
    private const PROFILE_KEYS = [
        'stall_no', 'phone_code', 'phone', 'rating', 'featured', 'premium', 'about',
        'street', 'city', 'state', 'zip', 'country', 'location_url', 'website_url',
        'tags', 'filter_id', 'filter_selections', 'spotlight_type', 'spotlight_url', 'spotlight_file_id',
        'cta', 'social', 'contact', 'entitlements',
    ];

    private const WITH = ['package', 'members.contact', 'products', 'documents', 'projects'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Exhibitor::with('package')->withCount('members')->latest('id');

        if ($request->filled('event')) {
            $event = Event::where('uuid', $request->string('event'))->firstOrFail();
            $query->where('event_id', $event->id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        return ExhibitorResource::collection($query->get());
    }

    public function show(string $uuid): JsonResponse
    {
        $exhibitor = Exhibitor::with(self::WITH)->withCount('members')->where('uuid', $uuid)->firstOrFail();

        return response()->json(['data' => new ExhibitorResource($exhibitor)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->nullifyEmpty($request, ['type', 'email', 'package_id', 'logo_file_id']);

        $data = $request->validate([
            'event' => ['required', 'string'],
            'type' => ['nullable', 'in:exhibitor,sponsor'],
            'name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email'],   // exhibitor-admin login email
            'package_id' => ['required', 'integer', 'exists:exhibitor_packages,id'],
            'logo_file_id' => ['nullable', 'integer'],
        ]);

        $event = Event::where('uuid', $data['event'])->firstOrFail();

        $email = $data['email'] ?? null;
        if ($email !== null) {
            $this->assertEmailUniqueForEvent($email, $event->id);
        }

        $exhibitor = new Exhibitor([
            'event_id' => $event->id,
            'type' => $data['type'] ?? 'exhibitor',
            'name' => $data['name'],
            'email' => $email,
            'slug' => $this->uniqueSlug($data['name'], $event->id),
            'package_id' => $request->filled('package_id') ? $data['package_id'] : null,
            'logo_file_id' => $request->filled('logo_file_id') ? $data['logo_file_id'] : null,
            'website' => $request->input('website_url'),
            'profile_data' => $this->profileFrom($request),
        ]);
        // status (governance) + created_by (attribution) are not $fillable.
        $exhibitor->forceFill(['status' => 'active', 'created_by' => $request->user()->id])->save();

        // Provision the exhibitor-admin login and email them a 6-digit access code.
        $adminInvited = false;
        if (! empty($email)) {
            $adminInvited = $this->provisionAdmin($exhibitor, $email);
        }

        return response()->json([
            'data' => new ExhibitorResource($exhibitor->fresh(self::WITH)->loadCount('members')),
            'admin_invited' => $adminInvited,
        ], 201);
    }

    public function update(string $uuid, Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::where('uuid', $uuid)->firstOrFail();

        $this->nullifyEmpty($request, ['type', 'package_id', 'logo_file_id']);

        $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'type' => ['sometimes', 'in:exhibitor,sponsor'],
            'package_id' => ['sometimes', 'nullable', 'integer', 'exists:exhibitor_packages,id'],
            'logo_file_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'in:draft,active,suspended'],
        ]);

        // Real columns — only those actually present in the request.
        $cols = [];
        foreach (['name', 'type', 'package_id', 'logo_file_id', 'status'] as $k) {
            if ($request->has($k)) {
                $cols[$k] = $request->input($k) !== '' ? $request->input($k) : null;
            }
        }
        if ($request->has('website_url')) {
            $cols['website'] = $request->input('website_url');
        }

        // Merge the profile fields present in this request over the existing ones,
        // so a single tab (e.g. Permissions) can save without wiping the rest.
        $profile = array_merge($exhibitor->profile_data ?? [], $this->profileFrom($request));

        // status (governance) + updated_by (attribution) are not $fillable.
        $privileged = ['updated_by' => $request->user()->id];
        if (array_key_exists('status', $cols)) {
            $privileged['status'] = $cols['status'];
            unset($cols['status']);
        }
        $exhibitor->fill($cols + ['profile_data' => $profile]);
        $exhibitor->forceFill($privileged)->save();

        return response()->json(['data' => new ExhibitorResource($exhibitor->fresh(self::WITH)->loadCount('members'))]);
    }

    /**
     * Reset the exhibitor-admin login password. Two modes (matching the UI):
     *  - auto:   generate a strong password and return it so the organizer can
     *            view/copy it once.
     *  - manual: set a specific password supplied by the organizer.
     * Optionally flags the login to change the password on next sign-in. If the
     * exhibitor has no login yet but does have an admin email, one is provisioned
     * on the spot with this password (no 6-digit code email).
     */
    public function resetPassword(string $uuid, Request $request): JsonResponse
    {
        $exhibitor = Exhibitor::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'mode' => ['required', 'in:auto,manual'],
            'password' => ['required_if:mode,manual', 'nullable', 'string', 'min:8'],
            'must_change' => ['nullable', 'boolean'],
        ]);

        $password = $data['mode'] === 'auto'
            ? Str::password(14, symbols: false)
            : $data['password'];

        $user = $this->resolveAdminUser($exhibitor);

        if (! $user) {
            // No login yet — provision one if we have an email to attach it to.
            if (empty($exhibitor->email)) {
                throw ValidationException::withMessages([
                    'exhibitor' => 'This exhibitor has no admin email to attach a login to. Add an admin email first.',
                ]);
            }
            $contact = Contact::firstOrCreate(
                ['email' => $exhibitor->email],
                ['first_name' => $exhibitor->name],
            );
            $user = User::firstOrCreate(
                ['email' => $exhibitor->email],
                ['name' => $exhibitor->name, 'email_verified_at' => now()],
            );
            $contact->update(['user_id' => $user->id]);
            $member = ExhibitorMember::firstOrNew(
                ['exhibitor_id' => $exhibitor->id, 'contact_id' => $contact->id],
            );
            $member->forceFill(['role' => 'admin'])->save(); // role: privileged, not $fillable
            $exhibitor->update(['admin_contact_id' => $contact->id]);
        }

        $user->forceFill([
            'password' => $password,                              // hashed by the model cast
            'must_change_password' => (bool) ($data['must_change'] ?? false),
        ])->save();

        return response()->json([
            'data' => [
                'email' => $user->email,
                // Only echo the secret for the auto-generate flow (one-time view).
                'password' => $data['mode'] === 'auto' ? $password : null,
                'must_change' => (bool) ($data['must_change'] ?? false),
            ],
        ]);
    }

    /** The exhibitor's admin login User, if one exists. */
    protected function resolveAdminUser(Exhibitor $exhibitor): ?User
    {
        $contactId = $exhibitor->admin_contact_id;

        if ($contactId) {
            $contact = Contact::find($contactId);
            if ($contact?->user_id) {
                return User::find($contact->user_id);
            }
        }

        // Fall back to any admin member that has a login.
        $member = ExhibitorMember::with('contact')
            ->where('exhibitor_id', $exhibitor->id)
            ->where('role', 'admin')
            ->get()
            ->first(fn ($m) => $m->contact?->user_id);

        if ($member?->contact?->user_id) {
            return User::find($member->contact->user_id);
        }

        // Last resort: the exhibitor's email mapped to a contact with a login.
        if ($exhibitor->email) {
            $contact = Contact::where('email', $exhibitor->email)->first();
            if ($contact?->user_id) {
                return User::find($contact->user_id);
            }
        }

        return null;
    }

    /** Collect the profile_data fields actually present in the request. */
    protected function profileFrom(Request $request): array
    {
        return $request->only(self::PROFILE_KEYS);
    }

    /** Treat empty-string inputs as null so nullable/typed rules pass. */
    protected function nullifyEmpty(Request $request, array $keys): void
    {
        foreach ($keys as $key) {
            if ($request->input($key) === '') {
                $request->merge([$key => null]);
            }
        }
    }

    /** An exhibitor email may appear at most once per event (case-insensitive). */
    protected function assertEmailUniqueForEvent(string $email, int $eventId): void
    {
        $taken = Exhibitor::where('event_id', $eventId)
            ->whereRaw('lower(email) = ?', [Str::lower($email)])
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'email' => 'An exhibitor with this email already exists for this event.',
            ]);
        }
    }

    protected function uniqueSlug(string $name, int $eventId): string
    {
        $base = Str::slug($name) ?: 'exhibitor';
        $slug = $base;
        $i = 1;
        while (Exhibitor::where('event_id', $eventId)->where('slug', $slug)->withTrashed()->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    /**
     * Create (or refresh) the exhibitor-admin login for the given email and mail
     * them a fresh 6-digit access code. Runs under the organizer's tenant GUC, so
     * the contact/member writes satisfy RLS; the `users` row is global. Returns
     * whether the invite email was dispatched.
     */
    protected function provisionAdmin(Exhibitor $exhibitor, string $email): bool
    {
        $contact = Contact::firstOrCreate(
            ['email' => $email],
            ['first_name' => $exhibitor->name],
        );

        // 6-digit numeric access code, used as the initial password.
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if (! $contact->user_id) {
            $user = User::create([
                'name' => $exhibitor->name,
                'email' => $email,
                'password' => $code,           // hashed by the model cast
            ]);
            $contact->update(['user_id' => $user->id]);
        } else {
            // Existing login → reset to the new code so they can sign in.
            User::whereKey($contact->user_id)->first()
                ?->forceFill(['password' => $code])->save();
        }

        // The `admin` membership is what makes them an exhibitor admin (§6.3).
        // role is privileged (not $fillable) → forceFill.
        $member = ExhibitorMember::firstOrNew(
            ['exhibitor_id' => $exhibitor->id, 'contact_id' => $contact->id],
        );
        $member->forceFill(['role' => 'admin'])->save();

        $exhibitor->update(['admin_contact_id' => $contact->id]);

        return $this->emailAccessCode($exhibitor, $email, $code);
    }

    /** Mail the 6-digit access code; never let a mail failure fail the request. */
    protected function emailAccessCode(Exhibitor $exhibitor, string $email, string $code): bool
    {
        $eventName = $exhibitor->event?->name ?? 'the event';

        $body = "Hello,\n\n"
            ."An exhibitor admin account has been created for \"{$exhibitor->name}\" ({$eventName}).\n\n"
            ."Sign in with:\n"
            ."  Email:       {$email}\n"
            ."  Access code: {$code}\n\n"
            ."Use the access code as your password, then change it after signing in.\n";

        try {
            Mail::raw($body, function ($m) use ($email) {
                $m->to($email)->subject('Your EventOS exhibitor admin access code');
            });

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
