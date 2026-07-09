<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SessionResource;
use App\Models\BreakoutRoom;
use App\Models\Event;
use App\Models\EventAd;
use App\Models\EventSetting;
use App\Models\Exhibitor;
use App\Models\Form;
use App\Models\Participation;
use App\Models\Session;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Public per-event microsite bootstrap (no auth, no tenant).
 *
 * The event SPA (eventos-event) is served per-subdomain — <sub>.<apex>, e.g.
 * edu.expouse.test. The browser sends the resolved subdomain in the
 * `X-Event-Subdomain` header (or ?subdomain= for local dev), and this
 * controller turns it into that event's PUBLIC config so the branded
 * landing/login page can render before anyone signs in.
 *
 * Every read runs on the `pgsql_admin` connection (BYPASSRLS) because no tenant
 * is chosen yet; only PUBLISHED events are ever exposed (drafts stay private).
 */
class PublicSiteController extends Controller
{
    /** GET /api/v1/public/site — resolve subdomain → published event public config. */
    public function show(Request $request): JsonResponse
    {
        $resolved = $this->resolvePublishedEvent($request);

        if ($resolved === null) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        [$event, $setting, $sub] = $resolved;

        $branding = $setting->branding ?? [];
        $theme = $setting->theme ?? [];
        $login = $setting->login ?? [];
        $seo = $setting->seo ?? [];
        $cover = $event->coverFile;

        $regForm = Form::on('pgsql_admin')
            ->where('event_id', $event->id)
            ->where('key', 'registration')
            ->where('status', 'published')
            ->latest('id')
            ->first();

        return response()->json([
            'data' => [
                'event' => [
                    'uuid' => $event->uuid,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'description' => $event->description,
                    'format' => $event->format,
                    'starts_at' => $event->starts_at?->toIso8601String(),
                    'ends_at' => $event->ends_at?->toIso8601String(),
                    'timezone' => $event->timezone,
                    'location' => $event->meta['location'] ?? null,
                    'cover_url' => $cover ? Storage::disk($cover->disk)->url($cover->path) : null,
                ],
                'branding' => [
                    'logo_url' => $branding['logo_url'] ?? null,
                    'primary' => $theme['primary'] ?? '#6352e7',
                    'accent' => $theme['accent'] ?? '#22d3ee',
                    'banners' => $this->publicBanners($branding),
                    'login' => [
                        'type' => $branding['login']['type'] ?? 'banner',
                        'banner_url' => $branding['login']['banner_url'] ?? null,
                        'video_url' => $branding['login']['video_url'] ?? null,
                        'website_url' => $branding['login']['website_url'] ?? null,
                    ],
                ],
                'login' => [
                    'methods' => $login['methods'] ?? [],
                    'require_login' => (bool) ($login['require_login'] ?? false),
                ],
                'seo' => [
                    'meta_title' => $seo['meta_title'] ?? null,
                    'meta_description' => $seo['meta_description'] ?? null,
                    'favicon_url' => $seo['favicon_url'] ?? null,
                ],
                'subdomain' => $sub,
                'registration_form_uuid' => $regForm?->uuid,
                'powered_by' => 'EXPOUSE',
            ],
        ]);
    }

    /**
     * POST /api/v1/public/check-email — does this person already have a login?
     *
     * Powers the single-field "enter your email to login/signup" flow: a known
     * account with a password → show the password step; otherwise → send them
     * to the event's registration form. Returns only booleans (no PII leak).
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::on('pgsql_admin')
            ->where('email', $data['email'])
            ->first();

        return response()->json([
            'exists' => (bool) $user,
            'has_password' => (bool) ($user && $user->password),
        ]);
    }

    /**
     * GET /api/v1/public/reception — the post-login attendee home ("Reception")
     * for the event this subdomain resolves to. Aggregates the public content
     * the reception page renders: about + socials, hero banners, reception ads,
     * featured sessions/speakers, exhibitors and sponsors. All reads run on
     * `pgsql_admin` (no tenant chosen) and only PUBLISHED events are exposed.
     *
     * Per-user data (the visitor's own meetings) is NOT here — the SPA loads
     * that separately from the authed participant endpoint.
     */
    public function reception(Request $request): JsonResponse
    {
        $resolved = $this->resolvePublishedEvent($request);

        if ($resolved === null) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        [$event, $setting] = $resolved;

        $branding = $setting->branding ?? [];
        $cover = $event->coverFile;

        // Featured first, then the soonest upcoming — capped so the carousels
        // stay light. Speakers eager-loaded to fill each session card.
        $sessions = Session::on('pgsql_admin')
            ->with(['speakers.contact', 'track', 'room'])
            ->where('event_id', $event->id)
            ->orderByRaw("COALESCE((meta->>'is_featured')::boolean, false) DESC")
            ->orderByRaw('starts_at IS NULL, starts_at ASC')
            ->limit(6)
            ->get();

        $speakers = Participation::on('pgsql_admin')
            ->with('contact')
            ->where('event_id', $event->id)
            ->speakers()
            ->orderByRaw("COALESCE((profile_data->>'is_featured')::boolean, false) DESC")
            ->orderByRaw("COALESCE((profile_data->>'sort_order')::int, 0) ASC")
            ->limit(12)
            ->get()
            ->filter(fn (Participation $p) => ($p->profile_data['is_public'] ?? true))
            ->map(fn (Participation $p) => $this->formatSpeaker($p))
            ->values();

        $exhibitors = Exhibitor::on('pgsql_admin')
            ->with('logoFile')
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->exhibitors()
            ->orderByDesc('tier_rank')
            ->limit(12)
            ->get()
            ->map(fn (Exhibitor $e) => $this->formatExhibitor($e))
            ->values();

        $sponsors = Exhibitor::on('pgsql_admin')
            ->with('logoFile')
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->sponsors()
            ->orderByDesc('tier_rank')
            ->limit(12)
            ->get()
            ->map(fn (Exhibitor $e) => $this->formatExhibitor($e))
            ->values();

        // Active reception-targeted ads, split into the horizontal strip
        // (main/featured placements) and the right-rail cards (content).
        $now = now();
        $ads = EventAd::on('pgsql_admin')
            ->where('event_id', $event->id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('start_at')->orWhere('start_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', $now))
            ->get()
            ->filter(function (EventAd $ad) {
                $pages = $ad->targeted_pages;

                return empty($pages) || in_array('reception', $pages, true);
            });

        $formatAd = fn (EventAd $ad) => [
            'id' => $ad->uuid ?? $ad->id,
            'title' => $ad->title,
            'placement' => $ad->placement,
            'images' => $ad->images ?? [],
        ];

        return response()->json([
            'data' => [
                'about' => [
                    'name' => $event->name,
                    'description' => $event->description,
                    'format' => $event->format,
                    'starts_at' => $event->starts_at?->toIso8601String(),
                    'ends_at' => $event->ends_at?->toIso8601String(),
                    'timezone' => $event->timezone,
                    'location' => $event->meta['location'] ?? null,
                    'logo_url' => $branding['logo_url'] ?? null,
                    'cover_url' => $cover ? Storage::disk($cover->disk)->url($cover->path) : null,
                    'social' => $setting->social ?? (object) [],
                ],
                'event' => [
                    'uuid' => $event->uuid,
                    'name' => $event->name,
                    'slug' => $event->slug,
                ],
                'banners' => $this->publicBanners($branding),
                'ads' => [
                    'strip' => $ads->whereIn('placement', ['main', 'featured'])->map($formatAd)->values(),
                    'sidebar' => $ads->where('placement', 'content')->map($formatAd)->values(),
                ],
                'sessions' => SessionResource::collection($sessions),
                'speakers' => $speakers,
                'exhibitors' => $exhibitors,
                'sponsors' => $sponsors,
            ],
        ]);
    }

    /**
     * GET /api/v1/public/speakers — the attendee-facing speaker directory
     * ("Speakers" tab) for the event this subdomain resolves to. Public read
     * (published events only); only PUBLIC speakers are exposed. Returns the
     * full list (client sorts/searches) plus the event's speaker categories.
     */
    public function speakers(Request $request): JsonResponse
    {
        $resolved = $this->resolvePublishedEvent($request);

        if ($resolved === null) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        [$event] = $resolved;

        $speakers = Participation::on('pgsql_admin')
            ->with('contact')
            ->where('event_id', $event->id)
            ->speakers()
            ->orderByRaw("COALESCE((profile_data->>'is_featured')::boolean, false) DESC")
            ->orderByRaw("COALESCE((profile_data->>'sort_order')::int, 0) ASC")
            ->orderBy('created_at')
            ->get()
            ->filter(fn (Participation $p) => ($p->profile_data['is_public'] ?? true))
            ->map(fn (Participation $p) => $this->formatSpeakerFull($p))
            ->values();

        return response()->json([
            'data' => [
                'speakers' => $speakers,
                'categories' => $event->meta['speaker_categories'] ?? [],
            ],
        ]);
    }

    /**
     * GET /api/v1/public/exhibitors — the attendee-facing exhibitor & sponsor
     * directory ("Exhibitors" tab) for the event this subdomain resolves to.
     * Public read (published events only); only ACTIVE exhibitors are exposed.
     * Returns the full list (client sorts/searches/filters) with each booth's
     * products and public documents inlined, plus the derived category facets.
     */
    public function exhibitors(Request $request): JsonResponse
    {
        $resolved = $this->resolvePublishedEvent($request);

        if ($resolved === null) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        [$event, $setting] = $resolved;

        $exhibitors = Exhibitor::on('pgsql_admin')
            ->with(['logoFile', 'products', 'documents'])
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->orderByDesc('tier_rank')
            ->orderBy('name')
            ->get();

        // Which filter options are actually chosen by at least one booth:
        // filterId → set of option strings. Facets not in use are hidden.
        $usedOptions = [];
        foreach ($exhibitors as $e) {
            foreach (($e->profile_data['filter_selections'] ?? []) as $fid => $groups) {
                foreach ((array) $groups as $opts) {
                    foreach ((array) $opts as $opt) {
                        $usedOptions[$fid][(string) $opt] = true;
                    }
                }
            }
        }

        $list = $exhibitors->map(fn (Exhibitor $e) => $this->formatExhibitorFull($e))->values();

        return response()->json([
            'data' => [
                'exhibitors' => $list->where('type', 'exhibitor')->values(),
                'sponsors' => $list->where('type', 'sponsor')->values(),
                'categories' => $list->pluck('category')->filter()->unique()->sort()->values(),
                // Event edition year (shown under each booth's type on the card).
                'year' => $event->starts_at?->year,
                // The organizer's "Manage Filters" facets (title → headings → options),
                // trimmed to only the options actually used by a booth so the attendee
                // rail never shows a filter no exhibitor is tagged with.
                'filters' => $this->publicFilters($setting->filters ?? [], $usedOptions),
            ],
        ]);
    }

    /**
     * GET /api/v1/public/exhibitors/{uuid} — a single booth's full profile for
     * the exhibitor details page (banner, about, projects, members, contact,
     * CTA, map, brochures). Public read; active exhibitors only.
     */
    public function exhibitor(Request $request, string $uuid): JsonResponse
    {
        $resolved = $this->resolvePublishedEvent($request);

        if ($resolved === null) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        [$event] = $resolved;

        $exhibitor = Exhibitor::on('pgsql_admin')
            ->with(['logoFile', 'products', 'documents', 'projects', 'members.contact'])
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->where('uuid', $uuid)
            ->first();

        if (! $exhibitor) {
            return response()->json(['message' => 'Exhibitor not found.'], 404);
        }

        return response()->json(['data' => $this->formatExhibitorDetail($exhibitor)]);
    }

    /**
     * GET /api/v1/public/sessions — the attendee-facing agenda ("Sessions" tab)
     * for the event this subdomain resolves to. Public read (published events
     * only). Returns the full session list (with speakers, track, room, tags,
     * sponsors and stream/replay links) plus the derived facets the page filters
     * by: the event's day range, the tracks in use, and the distinct tags and
     * speakers. All reads run on `pgsql_admin` (no tenant chosen yet).
     */
    public function sessions(Request $request): JsonResponse
    {
        $resolved = $this->resolvePublishedEvent($request);

        if ($resolved === null) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        [$event] = $resolved;

        $sessions = Session::on('pgsql_admin')
            ->with(['speakers.contact', 'track', 'room'])
            ->where('event_id', $event->id)
            ->orderByRaw('starts_at IS NULL, starts_at ASC')
            ->get();

        // Tracks actually used by these sessions, de-duped and ordered as the
        // organizer sorted them — these become the "All Tracks / …" filter tabs.
        $tracks = $sessions
            ->pluck('track')
            ->filter()
            ->unique('id')
            ->sortBy('sort_order')
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])
            ->values();

        // Distinct tags across all sessions (Advance Filter › Tags).
        $tags = $sessions
            ->flatMap(fn (Session $s) => $s->meta['tags'] ?? [])
            ->filter()
            ->unique()
            ->values();

        // Distinct speakers across all sessions (Advance Filter › Speakers).
        $speakers = $sessions
            ->flatMap(fn (Session $s) => $s->speakers)
            ->unique('uuid')
            ->map(fn (Participation $p) => [
                'id' => $p->uuid,
                'name' => $p->contact?->fullName(),
                'image_url' => $p->profile_data['image_url'] ?? null,
            ])
            ->sortBy('name')
            ->values();

        return response()->json([
            'data' => [
                'event' => [
                    'uuid' => $event->uuid,
                    'name' => $event->name,
                    'timezone' => $event->resolvedTimezone(),
                    'starts_at' => $event->starts_at?->toIso8601String(),
                    'ends_at' => $event->ends_at?->toIso8601String(),
                ],
                'tracks' => $tracks,
                'tags' => $tags,
                'speakers' => $speakers,
                'sessions' => SessionResource::collection($sessions),
            ],
        ]);
    }

    /**
     * GET /api/v1/public/rooms — the attendee-facing breakout room list for the
     * event this subdomain resolves to. Public read (published events only);
     * exposes only PUBLISHED, non-HIDDEN rooms and never leaks the access code
     * (only whether one is set). Joining/minting a media token is a separate,
     * authenticated call (POST /events/{event}/breakout-rooms/{room}/token).
     */
    public function rooms(Request $request): JsonResponse
    {
        $resolved = $this->resolvePublishedEvent($request);

        if ($resolved === null) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        [$event] = $resolved;

        $rooms = BreakoutRoom::on('pgsql_admin')
            ->where('event_id', $event->id)
            ->where('status', 'published')
            ->where('access_type', '!=', 'hidden')
            ->orderByDesc('id')
            ->get()
            ->map(fn (BreakoutRoom $r) => [
                'id' => $r->id,
                'uuid' => $r->uuid,
                'name' => $r->name,
                'description' => $r->description,
                'purpose' => $r->purpose,
                'type' => $r->type,
                'access_type' => $r->access_type,
                'has_access_code' => filled($r->access_code),
                'capacity' => $r->capacity,
                'poster_url' => $r->poster_url,
                'provider' => $r->provider,
                'meeting_url' => $r->provider === 'webrtc' ? null : $r->meeting_url,
                'starts_at' => $r->starts_at?->toIso8601String(),
                'ends_at' => $r->ends_at?->toIso8601String(),
            ])
            ->values();

        return response()->json(['data' => $rooms]);
    }

    /** A speaker card projection (mirrors SpeakerController::format). */
    protected function formatSpeaker(Participation $p): array
    {
        $profile = $p->profile_data ?? [];

        return [
            'id' => $p->uuid,
            'name' => $p->contact?->fullName(),
            'designation' => $profile['designation'] ?? '',
            'company' => $profile['company'] ?? '',
            'category' => $profile['category'] ?? '',
            'image_url' => $profile['image_url'] ?? null,
        ];
    }

    /** A full speaker projection for the Speakers directory (cards + detail). */
    protected function formatSpeakerFull(Participation $p): array
    {
        $profile = $p->profile_data ?? [];

        return [
            'id' => $p->uuid,
            'name' => $p->contact?->fullName(),
            'designation' => $profile['designation'] ?? '',
            'company' => $profile['company'] ?? '',
            'category' => $profile['category'] ?? '',
            'bio' => $profile['bio'] ?? '',
            'image_url' => $profile['image_url'] ?? null,
            'is_featured' => (bool) ($profile['is_featured'] ?? false),
            'social' => array_filter([
                'linkedin' => $profile['linkedin'] ?? null,
                'twitter' => $profile['twitter'] ?? null,
                'facebook' => $profile['facebook'] ?? null,
                'instagram' => $profile['instagram'] ?? null,
            ]),
        ];
    }

    /** An exhibitor|sponsor card projection. */
    protected function formatExhibitor(Exhibitor $e): array
    {
        $logo = $e->logoFile;
        $profile = $e->profile_data ?? [];

        return [
            'id' => $e->uuid,
            'name' => $e->name,
            'type' => $e->type,
            'website' => $e->website,
            'booth' => $profile['booth'] ?? ($profile['stand'] ?? null),
            'logo_url' => $logo ? Storage::disk($logo->disk)->url($logo->path) : ($profile['logo_url'] ?? null),
        ];
    }

    /** A full exhibitor projection for the directory (cards + detail modal). */
    protected function formatExhibitorFull(Exhibitor $e): array
    {
        $logo = $e->logoFile;
        $profile = $e->profile_data ?? [];

        return [
            'id' => $e->uuid,
            'name' => $e->name,
            'type' => $e->type,
            'category' => $profile['category'] ?? '',
            'description' => $e->description ?? ($profile['description'] ?? ''),
            'website' => $profile['website_url'] ?? $e->website,
            'booth' => $profile['booth'] ?? ($profile['stand'] ?? null),
            'tier_rank' => (int) $e->tier_rank,
            'is_featured' => (bool) ($profile['is_featured'] ?? false),
            'logo_url' => $logo ? Storage::disk($logo->disk)->url($logo->path) : ($profile['logo_url'] ?? null),
            'social' => array_filter([
                'linkedin' => $profile['linkedin'] ?? null,
                'twitter' => $profile['twitter'] ?? null,
                'facebook' => $profile['facebook'] ?? null,
                'instagram' => $profile['instagram'] ?? null,
            ]),
            'products' => $e->products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'price' => $p->price_cents !== null ? round($p->price_cents / 100, 2) : null,
                'image_url' => ($p->meta['image_url'] ?? null) ?: $this->fileUrl($p->image_file_id),
            ])->values(),
            'documents' => $e->documents
                ->filter(fn ($d) => ($d->visibility ?? 'all') === 'all')
                ->map(fn ($d) => ['id' => $d->id, 'title' => $d->title, 'url' => $d->url])
                ->values(),
            // filterId → heading → chosen options (from the admin filter picker).
            'filter_selections' => (object) ($profile['filter_selections'] ?? []),
        ];
    }

    /** The full booth profile for the exhibitor details page. */
    protected function formatExhibitorDetail(Exhibitor $e): array
    {
        $profile = $e->profile_data ?? [];
        $contact = $profile['contact'] ?? [];

        $phone = trim(($contact['phone_code'] ?? '').' '.($contact['phone'] ?? ''))
            ?: trim(($profile['phone_code'] ?? '').' '.($profile['phone'] ?? ''));
        $address = implode(', ', array_filter([
            $profile['street'] ?? null, $profile['city'] ?? null,
            $profile['state'] ?? null, $profile['country'] ?? null,
        ]));

        return array_merge($this->formatExhibitorFull($e), [
            'about' => $profile['about'] ?? ($e->description ?? ''),
            'can_rate' => (bool) ($profile['rating'] ?? false),
            'spotlight' => [
                'type' => $profile['spotlight_type'] ?? 'image',
                'url' => $profile['spotlight_url'] ?? null,
            ],
            'contact' => [
                'phone' => $phone ?: null,
                'email' => $contact['email'] ?? $e->email,
                'full_name' => $contact['full_name'] ?? null,
                'position' => $contact['position'] ?? null,
                'company_name' => $contact['company_name'] ?? null,
            ],
            'cta' => array_values(array_filter(
                (array) ($profile['cta'] ?? []),
                fn ($c) => is_array($c) && (($c['label'] ?? '') !== '' || ($c['value'] ?? '') !== ''),
            )),
            'location' => [
                'address' => $address ?: null,
                'url' => $profile['location_url'] ?? null,
            ],
            'members' => $e->members->map(function ($m) {
                $c = $m->contact;

                return [
                    'name' => $c ? trim(($c->first_name ?? '').' '.($c->last_name ?? '')) : 'Member',
                    'designation' => $c?->job_title ?: ucfirst((string) $m->role),
                    'company' => $c?->company,
                    'avatar_url' => $this->fileUrl($c?->photo_file_id) ?? ($c?->profile_data['avatar_url'] ?? null),
                ];
            })->values(),
            'projects' => $e->projects->map(fn ($p) => [
                'name' => $p->name,
                'description' => $p->description,
                'image_url' => ($p->meta['image_url'] ?? null) ?: $this->fileUrl($p->meta['image_file_id'] ?? null),
            ])->values(),
        ]);
    }

    /**
     * Normalise the event's configured filters for the public rail: keep only
     * id/title/headings(heading, options), and within each heading keep only
     * options a booth actually uses. Filters/headings left with no in-use
     * option are dropped, so the rail never shows an unused facet.
     *
     * @param  array<string, array<string, bool>>  $usedOptions  filterId → {option: true}
     */
    protected function publicFilters(array $filters, array $usedOptions = []): array
    {
        return collect($filters)
            ->map(function ($f) use ($usedOptions) {
                $fid = $f['id'] ?? '';
                $used = $usedOptions[$fid] ?? [];

                $headings = collect($f['headings'] ?? [])
                    ->map(fn ($h) => [
                        'heading' => $h['heading'] ?? '',
                        'options' => array_values(array_filter(
                            $h['options'] ?? [],
                            fn ($o) => trim((string) $o) !== '' && isset($used[(string) $o]),
                        )),
                    ])
                    ->filter(fn ($h) => count($h['options']) > 0)
                    ->values();

                return [
                    'id' => $fid,
                    'title' => $f['title'] ?? '',
                    'headings' => $headings->all(),
                ];
            })
            ->filter(fn ($f) => count($f['headings']) > 0)
            ->values()
            ->all();
    }

    /**
     * Community banners are stored either as plain URL strings (legacy) or as
     * {image, title, url, active} objects from the admin's banner form. The
     * public payload stays a flat list of image URLs, hidden banners excluded.
     */
    protected function publicBanners(array $branding): array
    {
        return collect($branding['banners'] ?? [])
            ->map(fn ($b) => is_array($b)
                ? (($b['active'] ?? true) ? ($b['image'] ?? null) : null)
                : $b)
            ->filter()
            ->values()
            ->all();
    }

    /** Resolve a soft file reference (image_file_id) to a public URL, or null. */
    protected function fileUrl(?int $fileId): ?string
    {
        if (! $fileId) {
            return null;
        }

        $file = \App\Models\File::on('pgsql_admin')->find($fileId);

        return $file ? Storage::disk($file->disk)->url($file->path) : null;
    }

    /**
     * Resolve the request's subdomain to its PUBLISHED event, or null.
     * Shared by show() and reception(); returns [Event, EventSetting, string $sub].
     *
     * @return array{0: Event, 1: EventSetting, 2: string}|null
     */
    protected function resolvePublishedEvent(Request $request): ?array
    {
        $sub = $this->subdomain($request);

        if ($sub === null) {
            return null;
        }

        $setting = EventSetting::on('pgsql_admin')
            ->where('domain->subdomain', $sub)
            ->first();

        if (! $setting) {
            return null;
        }

        /** @var Event|null $event */
        $event = Event::on('pgsql_admin')
            ->with('coverFile')
            ->where('id', $setting->event_id)
            ->first();

        // Never leak an unpublished (draft) event through the public site.
        if (! $event || $event->status !== 'published') {
            return null;
        }

        return [$event, $setting, $sub];
    }

    /** Subdomain from the SPA header, falling back to ?subdomain= for local dev. */
    protected function subdomain(Request $request): ?string
    {
        $sub = $request->header('X-Event-Subdomain') ?: $request->query('subdomain');
        $sub = is_string($sub) ? strtolower(trim($sub)) : null;

        return $sub !== '' ? $sub : null;
    }
}
