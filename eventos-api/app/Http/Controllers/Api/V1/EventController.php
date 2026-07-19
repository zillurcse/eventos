<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\NormalizesTimestamps;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\SessionResource;
use App\Models\Event;
use App\Models\BadgeDesign;
use App\Models\BreakoutRoom;
use App\Models\EmailTemplate;
use App\Models\EventSetting;
use App\Models\Exhibitor;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\Participation;
use App\Models\Session;
use App\Services\Auth\EventAccess;
use App\Services\Email\EventTemplateSeeder;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Events aggregate root (architecture §6.3). Times are stored/returned as UTC;
 * the IANA timezone drives convert-on-display (§6.3.1).
 */
class EventController extends Controller
{
    use NormalizesTimestamps;

    public function index(): AnonymousResourceCollection
    {
        return EventResource::collection(
            Event::withCount('sessions', 'participations')->with('coverFile')->latest('id')->paginate(20)
        );
    }

    public function show(string $uuid): JsonResponse
    {
        $event = Event::with('tracks', 'coverFile')->where('uuid', $uuid)->firstOrFail();

        return response()->json(['data' => new EventResource($event)]);
    }

    public function store(Request $request, TenantContext $tenant, EventTemplateSeeder $templateSeeder): JsonResponse
    {
        $data = $this->validateEvent($request, creating: true);
        $data = $this->utcDates($data, ['starts_at', 'ends_at']);

        $event = Event::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
            'format' => $data['format'] ?? 'venue',
            'timezone' => $data['timezone'] ?? 'UTC',
            'status' => 'draft',
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'is_public' => $data['is_public'] ?? false,
            'cover_file_id' => $data['cover_file_id'] ?? null,
            'meta' => array_filter([
                'location' => $data['location'] ?? null,
                'sector' => $data['sector'] ?? null,
            ]) ?: null,
            'created_by' => $request->user()->id,
        ]);

        $templateSeeder->seedForEvent($event, $tenant->id());

        // Give every new event a ready-to-use random subdomain (e.g. expo1234),
        // stored in event_settings.domain.subdomain. The organizer can change it
        // later on the Domain settings page.
        $setting = EventSetting::firstOrCreate(['event_id' => $event->id]);
        $setting->update([
            'domain' => array_merge($setting->domain ?? [], ['subdomain' => $this->uniqueSubdomain()]),
        ]);

        return response()->json(['data' => new EventResource($event->load('coverFile'))], 201);
    }

    public function update(string $uuid, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $data = $this->utcDates($this->validateEvent($request, creating: false), ['starts_at', 'ends_at']);

        // Location and sector are delivery-dependent/free-form and live in meta, not their own columns.
        $metaUpdates = [];
        if (array_key_exists('location', $data)) {
            $metaUpdates['location'] = $data['location'];
            unset($data['location']);
        }
        if (array_key_exists('sector', $data)) {
            $metaUpdates['sector'] = $data['sector'];
            unset($data['sector']);
        }
        if ($metaUpdates) {
            $data['meta'] = array_merge($event->meta ?? [], $metaUpdates);
        }

        $event->update($data + ['updated_by' => $request->user()->id]);

        return response()->json(['data' => new EventResource($event->fresh('coverFile'))]);
    }

    /**
     * Publish or unpublish the event (Content Hub → Publishing). Stamps
     * published_at on first publish; clears it when reverted to draft.
     */
    public function publish(string $uuid, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'status' => ['sometimes', 'in:draft,published'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $status = $data['status'] ?? 'published';

        $event->update([
            'status' => $status,
            'published_at' => $status === 'published' ? ($event->published_at ?? now()) : null,
            'is_public' => $data['is_public'] ?? $event->is_public,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['data' => new EventResource($event->fresh('coverFile'))]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        Event::where('uuid', $uuid)->firstOrFail()->delete();

        return response()->json(['message' => 'Event deleted.']);
    }

    /** Branding/theme + menu toggles for the event (event_settings row). */
    public function showSettings(string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $s = EventSetting::firstOrCreate(['event_id' => $event->id]);

        return response()->json(['data' => $this->settingsArray($s)]);
    }

    public function updateSettings(string $uuid, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'theme' => ['sometimes', 'array'],
            'theme.primary' => ['sometimes', 'nullable', 'string', 'max:9'],
            'theme.accent' => ['sometimes', 'nullable', 'string', 'max:9'],
            'theme.font_family' => ['sometimes', 'nullable', 'string', 'max:120'],
            'theme.mode' => ['sometimes', 'nullable', 'in:light,dark,auto'],
            'theme.header_style' => ['sometimes', 'nullable', 'in:solid,transparent,gradient'],
            'theme.button_radius' => ['sometimes', 'nullable', 'in:rounded,sharp,pill'],
            'modules_enabled' => ['sometimes', 'array'],
            'branding' => ['sometimes', 'array'],
            'login' => ['sometimes', 'array'],
            'login.methods' => ['sometimes', 'array'],
            'login.require_login' => ['sometimes', 'boolean'],
            'login.onboarding' => ['sometimes', 'boolean'],
            // An organizer's own OAuth app for a social channel (Settings › Access
            // authentication), keyed by provider (google/facebook/linkedin).
            'login.social_credentials' => ['sometimes', 'array'],
            'login.social_credentials.*.client_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'login.social_credentials.*.client_secret' => ['sometimes', 'nullable', 'string', 'max:500'],
            'navigation' => ['sometimes', 'array'],
            'seo' => ['sometimes', 'array'],
            'filters' => ['sometimes', 'array'],
            'banners' => ['sometimes', 'array'],
            'banners.*.id' => ['sometimes', 'string'],
            'banners.*.name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'banners.*.url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'banners.*.image_file_id' => ['sometimes', 'nullable', 'integer'],
            'banners.*.image_url' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'faqs' => ['sometimes', 'array'],
            'faqs.*.id' => ['sometimes', 'string'],
            'faqs.*.question' => ['sometimes', 'required', 'string', 'max:500'],
            'faqs.*.answer' => ['sometimes', 'required', 'string', 'max:5000'],
            'testimonials' => ['sometimes', 'array'],
            'testimonials.*.id' => ['sometimes', 'string'],
            'testimonials.*.name' => ['sometimes', 'required', 'string', 'max:200'],
            'testimonials.*.role' => ['sometimes', 'nullable', 'string', 'max:200'],
            'testimonials.*.company' => ['sometimes', 'nullable', 'string', 'max:200'],
            'testimonials.*.quote' => ['sometimes', 'required', 'string', 'max:2000'],
            'testimonials.*.rating' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:5'],
            'testimonials.*.avatar_file_id' => ['sometimes', 'nullable', 'integer'],
            'testimonials.*.avatar_url' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'testimonials.*.featured' => ['sometimes', 'boolean'],
            'event_highlights' => ['sometimes', 'array'],
            'event_highlights.*.id' => ['sometimes', 'string'],
            'event_highlights.*.name' => ['sometimes', 'required', 'string', 'max:200'],
            'event_highlights.*.icon' => ['sometimes', 'nullable', 'string', 'max:60'],
            'event_highlights.*.count' => ['sometimes', 'nullable', 'string', 'max:60'],
            'participant_profiles' => ['sometimes', 'array'],
            'participant_profiles.*.id' => ['sometimes', 'string'],
            'participant_profiles.*.name' => ['sometimes', 'required', 'string', 'max:200'],
            'participant_profiles.*.icon' => ['sometimes', 'nullable', 'string', 'max:60'],
            'participant_profiles.*.active' => ['sometimes', 'boolean'],
            'social' => ['sometimes', 'array'],
            'social.hashtag' => ['sometimes', 'nullable', 'string', 'max:120'],
            'social.facebook' => ['sometimes', 'nullable', 'string', 'max:500'],
            'social.twitter' => ['sometimes', 'nullable', 'string', 'max:500'],
            'social.linkedin' => ['sometimes', 'nullable', 'string', 'max:500'],
            'social.youtube' => ['sometimes', 'nullable', 'string', 'max:500'],
            'social.instagram' => ['sometimes', 'nullable', 'string', 'max:500'],
            'notifications' => ['sometimes', 'array'],
            'notifications.*.web' => ['sometimes', 'boolean'],
            'notifications.*.email' => ['sometimes', 'boolean'],
            'notifications.*.sms' => ['sometimes', 'boolean'],
            'chat' => ['sometimes', 'array'],
            'chat.*.attendee' => ['sometimes', 'boolean'],
            'chat.*.speaker' => ['sometimes', 'boolean'],
            'chat.*.exhibitor' => ['sometimes', 'boolean'],
            'chat.*.sponsor' => ['sometimes', 'boolean'],
            'meeting' => ['sometimes', 'array'],
            'meeting.permissions' => ['sometimes', 'array'],
            'meeting.permissions.*.attendee' => ['sometimes', 'boolean'],
            'meeting.permissions.*.speaker' => ['sometimes', 'boolean'],
            'meeting.permissions.*.exhibitor' => ['sometimes', 'boolean'],
            'meeting.permissions.*.sponsor' => ['sometimes', 'boolean'],
            'meeting.intelligent' => ['sometimes', 'boolean'],
            'meeting.slot_duration' => ['sometimes', 'integer', 'in:10,15,30'],
            // Places a meeting can be held on a venue/hybrid event ("Hall 4").
            // Empty list = attendees type their own.
            'meeting.locations' => ['sometimes', 'array', 'max:50'],
            'meeting.locations.*' => ['string', 'max:180'],
            'meeting.restrictions' => ['sometimes', 'array'],
            'meeting.restrictions.*.requests' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'meeting.restrictions.*.confirmed' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'lounge' => ['sometimes', 'array'],
            'lounge.enabled' => ['sometimes', 'boolean'],
            'lounge.slots_open_all' => ['sometimes', 'boolean'],
            'lounge.slots' => ['sometimes', 'array'],
            'lounge.slots.*' => ['array'],
            'lounge.slots.*.*' => ['string', 'max:40'],
            'lounge.attendee_tables_enabled' => ['sometimes', 'boolean'],
            'lounge.attendee_tables' => ['sometimes', 'array'],
            'lounge.attendee_tables.*.id' => ['sometimes', 'string', 'max:60'],
            'lounge.attendee_tables.*.name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'lounge.attendee_tables.*.capacity' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000'],
            'lounge.attendee_tables.*.image_file_id' => ['sometimes', 'nullable', 'integer'],
            'lounge.attendee_tables.*.image_url' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'lounge.attendee_tables.*.design' => ['sometimes', 'nullable', 'string', 'in:round,boardroom,lounge'],
            'lounge.attendee_tables.*.accent' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'lounge.exhibitor_tables_enabled' => ['sometimes', 'boolean'],
            'lounge.exhibitor_default_meetings' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'lounge.exhibitor_meetings' => ['sometimes', 'array'],
            'lounge.exhibitor_meetings.*' => ['integer', 'min:0', 'max:100000'],
            'lounge.exhibitor_order' => ['sometimes', 'array'],
            'lounge.exhibitor_order.*' => ['string', 'max:60'],
            'lounge.sponsor_tables_enabled' => ['sometimes', 'boolean'],
            'lounge.sponsor_default_meetings' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'lounge.sponsor_meetings' => ['sometimes', 'array'],
            'lounge.sponsor_meetings.*' => ['integer', 'min:0', 'max:100000'],
            'lounge.sponsor_order' => ['sometimes', 'array'],
            'lounge.sponsor_order.*' => ['string', 'max:60'],
            'communication' => ['sometimes', 'array'],
            'communication.functionality' => ['sometimes', 'array'],
            'communication.functionality.*' => ['array'],
            'communication.functionality.*.attendee' => ['sometimes', 'boolean'],
            'communication.functionality.*.speaker' => ['sometimes', 'boolean'],
            'communication.functionality.*.exhibitor' => ['sometimes', 'boolean'],
            'communication.functionality.*.sponsor' => ['sometimes', 'boolean'],
            'communication.moderation' => ['sometimes', 'array'],
            'communication.moderation.*' => ['boolean'],
            'communication.feed_tabs' => ['sometimes', 'array'],
            'communication.feed_tabs.*.key' => ['sometimes', 'string', 'max:60'],
            'communication.feed_tabs.*.label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'communication.feed_tabs.*.enabled' => ['sometimes', 'boolean'],
            'mobile_access_panel' => ['sometimes', 'array'],
            'mobile_access_panel.title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'mobile_access_panel.credentials_title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'mobile_access_panel.details' => ['sometimes', 'nullable', 'string', 'max:10000'],
            // Sender Details (Mail): default from-identity + optional custom SMTP.
            'sender' => ['sometimes', 'array'],
            'sender.from' => ['sometimes', 'nullable', 'email', 'max:180'],
            'sender.sender_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'sender.cc' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'sender.bcc' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'sender.reply_to' => ['sometimes', 'nullable', 'email', 'max:180'],
            'sender.is_smtp_config' => ['sometimes', 'boolean'],
            'sender.mail_mailer' => ['sometimes', 'nullable', 'string', 'max:60'],
            'sender.mail_host' => ['sometimes', 'nullable', 'string', 'max:180'],
            'sender.mail_port' => ['sometimes', 'nullable', 'string', 'max:10'],
            'sender.mail_encryption' => ['sometimes', 'nullable', 'string', 'max:20'],
            'sender.mail_username' => ['sometimes', 'nullable', 'string', 'max:180'],
            'sender.mail_password' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $s = EventSetting::firstOrCreate(['event_id' => $event->id]);

        // Domain is owned by DomainController (DNS verification state); never let a
        // generic settings save overwrite it, even if a client sends it.
        unset($data['domain']);

        // `social_available` is a read-only capability we compute on GET; a client
        // echoing it back must not persist it into the stored login blob.
        if (isset($data['login'])) {
            unset($data['login']['social_available']);
        }

        // Client secrets are write-only, same as the SMTP password below: the GET
        // response never echoes one back, so a blank value on save means "keep
        // what's stored" rather than "clear it".
        if (isset($data['login']['social_credentials'])) {
            $existingCreds = $s->login['social_credentials'] ?? [];
            foreach ($data['login']['social_credentials'] as $provider => $incoming) {
                if (($incoming['client_secret'] ?? '') === '') {
                    $incoming['client_secret'] = $existingCreds[$provider]['client_secret'] ?? null;
                }
                $data['login']['social_credentials'][$provider] = $incoming;
            }
            $data['login']['social_credentials'] = array_merge($existingCreds, $data['login']['social_credentials']);
        }

        // The SMTP password is write-only: a blank value on save keeps the stored
        // one (the GET response never echoes it back), so re-saving other fields
        // doesn't wipe the password.
        if (array_key_exists('sender', $data)) {
            $existing = $s->sender ?? [];
            $incoming = $data['sender'];
            if (($incoming['mail_password'] ?? '') === '' || ! array_key_exists('mail_password', $incoming)) {
                $incoming['mail_password'] = $existing['mail_password'] ?? null;
            }
            $data['sender'] = array_merge($existing, $incoming);
        }

        $s->fill($data)->save();

        return response()->json(['data' => $this->settingsArray($s->fresh())]);
    }

    protected function settingsArray(EventSetting $s): array
    {
        return [
            'theme' => (object) ($s->theme ?? []),
            'modules_enabled' => (object) ($s->modules_enabled ?? []),
            'branding' => (object) ($s->branding ?? []),
            // `social_available` isn't stored — it's derived from the organizer's
            // own credentials (if any) or the platform's, so the organizer can see
            // which social channels can actually be honoured before ticking them.
            'login' => (object) array_merge($s->login ?? [], [
                'social_available' => $this->socialAvailable($s),
                'social_credentials' => $this->socialCredentialsArray($s),
            ]),
            'domain' => (object) ($s->domain ?? []),
            'navigation' => (object) ($s->navigation ?? []),
            'seo' => (object) ($s->seo ?? []),
            'filters' => $s->filters ?? [],
            'banners' => $s->banners ?? [],
            'faqs' => $s->faqs ?? [],
            'testimonials' => $s->testimonials ?? [],
            'event_highlights' => $s->event_highlights ?? [],
            'participant_profiles' => $s->participant_profiles ?? [],
            'social' => (object) ($s->social ?? []),
            'notifications' => (object) ($s->notifications ?? []),
            'chat' => (object) ($s->chat ?? []),
            'meeting' => (object) ($s->meeting ?? []),
            'lounge' => (object) ($s->lounge ?? []),
            'communication' => (object) ($s->communication ?? []),
            'mobile_access_panel' => (object) ($s->mobile_access_panel ?? []),
            'sender' => (object) $this->senderArray($s),
        ];
    }

    /** Which social sign-in providers have an OAuth app behind them — theirs or the platform's. */
    protected function socialAvailable(EventSetting $s): array
    {
        $access = app(EventAccess::class);

        return collect(array_keys(EventAccess::SOCIAL_DRIVERS))
            ->mapWithKeys(fn (string $p) => [$p => $access->providerConfigured($p, $s)])
            ->all();
    }

    /** Client id is safe to echo back; the secret is write-only (see updateSettings). */
    protected function socialCredentialsArray(EventSetting $s): array
    {
        $stored = $s->login['social_credentials'] ?? [];

        return collect(array_keys(EventAccess::SOCIAL_DRIVERS))
            ->mapWithKeys(function (string $p) use ($stored) {
                $creds = $stored[$p] ?? [];

                return [$p => [
                    'client_id' => $creds['client_id'] ?? null,
                    'has_client_secret' => filled($creds['client_secret'] ?? null),
                ]];
            })
            ->all();
    }

    /** Sender config for the client — password is never echoed, only a flag. */
    protected function senderArray(EventSetting $s): array
    {
        $sender = $s->sender ?? [];
        $hasPassword = ! empty($sender['mail_password']);
        unset($sender['mail_password']);

        return $sender + ['has_smtp_password' => $hasPassword];
    }

    /** Update the username stored in event.meta.mobile_access. */
    public function updateCredentials(string $uuid, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $data = $request->validate(['username' => ['required', 'string', 'max:120']]);
        $meta = $event->meta ?? [];
        $meta['mobile_access']['username'] = $data['username'];
        $event->update(['meta' => $meta]);

        return response()->json(['data' => ['username' => $data['username']]]);
    }

    /** Event home: setup checklist + counts + mobile-access credentials. */
    public function overview(string $uuid): JsonResponse
    {
        $event = Event::with('coverFile')->where('uuid', $uuid)->firstOrFail();

        $exhibitors = Exhibitor::where('event_id', $event->id)->count();
        $sessions = Session::where('event_id', $event->id)->count();
        $team = Membership::where('status', 'active')->count();   // RLS-scoped to this org
        $badges = BadgeDesign::where('event_id', $event->id)->count();
        $emailTemplates = EmailTemplate::where('event_id', $event->id)->count();

        // Live tallies powering the Quick Actions cards.
        $quickCounts = [
            'users' => Participation::where('event_id', $event->id)->count(),
            'sessions' => $sessions,
            'meetings' => Meeting::where('event_id', $event->id)->count(),
            'booths' => $exhibitors,
            'rooms' => BreakoutRoom::where('event_id', $event->id)->count(),
            'upcoming_sessions' => Session::where('event_id', $event->id)
                ->where('starts_at', '>', now())->count(),
        ];

        // Generate + persist the mobile-app credentials once (stub, not yet
        // wired to a real mobile login).
        $meta = $event->meta ?? [];
        if (empty($meta['mobile_access'])) {
            $meta['mobile_access'] = [
                'username' => 'Admin'.random_int(1000, 9999).'-'.random_int(100, 999),
                'access_code' => Str::upper(Str::random(3)).'-'.Str::upper(Str::random(3)),
            ];
            $event->update(['meta' => $meta]);
        }

        $settings = EventSetting::firstOrCreate(['event_id' => $event->id]);
        $panel = $settings->mobile_access_panel ?? [];

        $checklist = [
            ['key' => 'basic', 'label' => 'Event Basic Information', 'done' => (bool) ($event->name && $event->starts_at && $event->ends_at), 'to' => 'details'],
            ['key' => 'branding', 'label' => 'Branding & Theme Setup', 'done' => (bool) $event->cover_file_id, 'to' => 'details/branding'],
            ['key' => 'exhibitors', 'label' => 'Exhibitors & Booth Details', 'done' => $exhibitors > 0, 'to' => 'showcase/exhibitors'],
            ['key' => 'badge', 'label' => 'Badge & QR Setup', 'done' => $badges > 0, 'to' => 'onsite/badge-templates'],
            ['key' => 'team', 'label' => 'Team Member Access Setup', 'done' => $team > 1, 'to' => 'team'],
            ['key' => 'email', 'label' => 'Email Template Configuration', 'done' => $emailTemplates > 0, 'to' => 'mail/email-builder'],
            ['key' => 'publish', 'label' => 'Review & Publish Event', 'done' => $event->status === 'published', 'to' => null],
        ];

        return response()->json(['data' => [
            'id' => $event->uuid,
            'name' => $event->name,
            'status' => $event->status,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'cover_url' => $event->coverFile ? Storage::disk($event->coverFile->disk)->url($event->coverFile->path) : null,
            'counts' => ['exhibitors' => $exhibitors, 'sessions' => $sessions],
            'quick_counts' => $quickCounts,
            'checklist' => $checklist,
            'completed' => collect($checklist)->where('done', true)->count(),
            'total' => count($checklist),
            'credentials' => $meta['mobile_access'],
            'mobile_access_panel' => [
                'title' => $panel['title'] ?? 'Setup your mobile access',
                'credentials_title' => $panel['credentials_title'] ?? 'Credentials',
                'details' => $panel['details'] ?? null,
            ],
        ]]);
    }

    /** Ordered agenda with tracks, rooms and speakers. */
    public function agenda(string $uuid): AnonymousResourceCollection
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $sessions = Session::with(['track', 'room', 'speakers.contact', 'event'])
            ->where('event_id', $event->id)
            ->orderBy('starts_at')
            ->get();

        return SessionResource::collection($sessions);
    }

    protected function validateEvent(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$req, 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'format' => ['nullable', 'in:venue,online,hybrid'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'is_public' => ['nullable', 'boolean'],
            // Free-text industry/category, stored in meta (no fixed enum on the backend).
            'sector' => ['nullable', 'string', 'max:120'],
            // Delivery-dependent location, stored in meta (address fields for in-person, url for online).
            'location' => ['nullable', 'array'],
            'location.venue' => ['nullable', 'string', 'max:255'],
            'location.street' => ['nullable', 'string', 'max:255'],
            'location.address_line_1' => ['nullable', 'string', 'max:255'],
            'location.address_line_2' => ['nullable', 'string', 'max:255'],
            'location.country' => ['nullable', 'string', 'max:120'],
            'location.state' => ['nullable', 'string', 'max:120'],
            'location.city' => ['nullable', 'string', 'max:120'],
            'location.zip' => ['nullable', 'string', 'max:20'],
            'location.url' => ['nullable', 'string', 'max:500'],
            // RLS scopes `files` to the active org, so exists() also enforces ownership.
            'cover_file_id' => ['nullable', 'integer', Rule::exists('files', 'id')],
        ]);
    }

    /**
     * A random, globally-unique subdomain like `expo1234` (prefix + 4–5 digits).
     * Checked on pgsql_admin because event_settings is RLS-scoped but subdomains
     * must be unique across every tenant.
     */
    protected function uniqueSubdomain(): string
    {
        do {
            $sub = 'expo'.random_int(1000, 99999);
        } while (EventSetting::on('pgsql_admin')->where('domain->subdomain', $sub)->exists());

        return $sub;
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'event';
        $slug = $base;
        $i = 1;
        while (Event::where('slug', $slug)->withTrashed()->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
