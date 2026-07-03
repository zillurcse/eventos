<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSetting;
use App\Models\Form;
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
        $sub = $this->subdomain($request);

        if ($sub === null) {
            return response()->json(['message' => 'No event subdomain supplied.'], 404);
        }

        $setting = EventSetting::on('pgsql_admin')
            ->where('domain->subdomain', $sub)
            ->first();

        if (! $setting) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        /** @var Event|null $event */
        $event = Event::on('pgsql_admin')
            ->with('coverFile')
            ->where('id', $setting->event_id)
            ->first();

        // Never leak an unpublished (draft) event through the public site.
        if (! $event || $event->status !== 'published') {
            return response()->json(['message' => 'Event not found.'], 404);
        }

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
                    'banners' => $branding['banners'] ?? [],
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

    /** Subdomain from the SPA header, falling back to ?subdomain= for local dev. */
    protected function subdomain(Request $request): ?string
    {
        $sub = $request->header('X-Event-Subdomain') ?: $request->query('subdomain');
        $sub = is_string($sub) ? strtolower(trim($sub)) : null;

        return $sub !== '' ? $sub : null;
    }
}
