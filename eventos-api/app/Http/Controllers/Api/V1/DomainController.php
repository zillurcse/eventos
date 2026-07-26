<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSetting;
use App\Services\DomainService;
use App\Support\PublicEventCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Per-event Domain settings: platform subdomain (<sub>.<apex>) + optional custom
 * domain with a real DNS verification flow (TXT ownership + CNAME/A routing).
 * State persists in event_settings.domain (JSONB); DomainService owns the rules.
 */
class DomainController extends Controller
{
    public function __construct(private DomainService $domains) {}

    public function show(string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $s = EventSetting::firstOrCreate(['event_id' => $event->id]);

        return response()->json(['data' => $this->payload($s)]);
    }

    /**
     * Live availability check before save.
     * GET /events/{uuid}/domain/check?subdomain=… | ?custom_domain=…
     */
    public function check(string $uuid, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $request->validate([
            'subdomain' => ['sometimes', 'nullable', 'string', 'max:120'],
            'custom_domain' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if ($request->filled('subdomain')) {
            $sub = $this->domains->normalizeSubdomain($request->input('subdomain'));
            if ($sub === null) {
                return response()->json(['data' => ['available' => false, 'reason' => 'Enter a subdomain.']]);
            }
            if (! $this->domains->isValidSubdomain($sub)) {
                return response()->json(['data' => [
                    'available' => false,
                    'reason' => 'Use 3–63 letters, numbers or hyphens (no leading/trailing hyphen).',
                    'normalized' => $sub,
                ]]);
            }
            if (in_array($sub, $this->domains->reserved(), true)) {
                return response()->json(['data' => [
                    'available' => false,
                    'reason' => 'That subdomain is reserved.',
                    'normalized' => $sub,
                ]]);
            }
            if ($this->domains->isSubdomainTaken($sub, $event->id)) {
                return response()->json(['data' => [
                    'available' => false,
                    'reason' => 'That subdomain is already taken.',
                    'normalized' => $sub,
                ]]);
            }

            return response()->json(['data' => [
                'available' => true,
                'reason' => null,
                'normalized' => $sub,
                'url' => "https://{$sub}.{$this->domains->apex()}",
            ]]);
        }

        if ($request->filled('custom_domain')) {
            $custom = $this->domains->normalizeCustomDomain($request->input('custom_domain'));
            if ($custom === null) {
                return response()->json(['data' => ['available' => false, 'reason' => 'Enter a domain.']]);
            }
            if (! $this->domains->isValidCustomDomain($custom)) {
                return response()->json(['data' => [
                    'available' => false,
                    'reason' => 'Enter a valid domain like events.yourcompany.com (not a '.$this->domains->apex().' address).',
                    'normalized' => $custom,
                ]]);
            }
            if ($this->domains->isCustomDomainTaken($custom, $event->id)) {
                return response()->json(['data' => [
                    'available' => false,
                    'reason' => 'That domain is already connected to another event.',
                    'normalized' => $custom,
                ]]);
            }

            return response()->json(['data' => [
                'available' => true,
                'reason' => null,
                'normalized' => $custom,
                'url' => "https://{$custom}",
            ]]);
        }

        throw ValidationException::withMessages([
            'subdomain' => 'Provide subdomain or custom_domain to check.',
        ]);
    }

    /** Save subdomain and/or custom domain. Changing the custom domain re-arms verification. */
    public function update(string $uuid, Request $request): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $s = EventSetting::firstOrCreate(['event_id' => $event->id]);
        $domain = $s->domain ?? [];
        $previousSub = $domain['subdomain'] ?? null;
        $previousCustom = $domain['custom_domain'] ?? null;

        $request->validate([
            'subdomain' => ['sometimes', 'nullable', 'string', 'max:120'],
            'custom_domain' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        // ── Subdomain ────────────────────────────────────────────────────
        if ($request->has('subdomain')) {
            $sub = $this->domains->normalizeSubdomain($request->input('subdomain'));
            if ($sub !== null) {
                if (! $this->domains->isValidSubdomain($sub)) {
                    throw ValidationException::withMessages(['subdomain' => 'Use 3–63 letters, numbers or hyphens (no leading/trailing hyphen).']);
                }
                if (in_array($sub, $this->domains->reserved(), true)) {
                    throw ValidationException::withMessages(['subdomain' => 'That subdomain is reserved.']);
                }
                if ($this->domains->isSubdomainTaken($sub, $event->id)) {
                    throw ValidationException::withMessages(['subdomain' => 'That subdomain is already taken.']);
                }
            }
            $domain['subdomain'] = $sub;
        }

        // ── Custom domain ────────────────────────────────────────────────
        if ($request->has('custom_domain')) {
            $custom = $this->domains->normalizeCustomDomain($request->input('custom_domain'));
            $previous = $domain['custom_domain'] ?? null;

            if ($custom === null) {
                // Cleared → drop verification state entirely.
                $domain = array_merge($domain, [
                    'custom_domain' => null, 'status' => DomainService::STATUS_UNCONFIGURED,
                    'verification_token' => null, 'verified_at' => null, 'checked_at' => null, 'error' => null,
                ]);
            } elseif ($custom !== $previous) {
                if (! $this->domains->isValidCustomDomain($custom)) {
                    throw ValidationException::withMessages(['custom_domain' => 'Enter a valid domain like events.yourcompany.com (not a '.$this->domains->apex().' address).']);
                }
                if ($this->domains->isCustomDomainTaken($custom, $event->id)) {
                    throw ValidationException::withMessages(['custom_domain' => 'That domain is already connected to another event.']);
                }
                // New/changed → fresh challenge, back to pending.
                $domain = array_merge($domain, [
                    'custom_domain' => $custom, 'status' => DomainService::STATUS_PENDING,
                    'verification_token' => $this->domains->newToken(),
                    'verified_at' => null, 'checked_at' => null, 'error' => null,
                ]);
            }
        }

        $s->update(['domain' => $domain]);

        // Bust public cache for both old and new identities.
        PublicEventCache::forgetSubdomain($previousSub);
        PublicEventCache::forgetSubdomain($domain['subdomain'] ?? null);
        PublicEventCache::forgetHost($previousCustom);
        PublicEventCache::forgetHost($domain['custom_domain'] ?? null);

        return response()->json(['data' => $this->payload($s->fresh())]);
    }

    /** Run live DNS checks for the pending custom domain and flip status. */
    public function verify(string $uuid): JsonResponse
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $s = EventSetting::firstOrCreate(['event_id' => $event->id]);
        $domain = $s->domain ?? [];

        $custom = $domain['custom_domain'] ?? null;
        $token = $domain['verification_token'] ?? null;
        if (! $custom || ! $token) {
            throw ValidationException::withMessages(['custom_domain' => 'Add a custom domain first.']);
        }

        $result = $this->domains->verify($custom, $token);

        $domain['status'] = $result['ok'] ? DomainService::STATUS_ACTIVE : DomainService::STATUS_FAILED;
        $domain['verified_at'] = $result['ok'] ? now()->toIso8601String() : ($domain['verified_at'] ?? null);
        $domain['checked_at'] = now()->toIso8601String();
        $domain['error'] = $result['error'];
        $s->update(['domain' => $domain]);

        PublicEventCache::forgetHost($custom);
        PublicEventCache::forgetSubdomain($domain['subdomain'] ?? null);

        return response()->json(['data' => $this->payload($s->fresh()), 'result' => $result]);
    }

    /** Everything the Domain settings UI renders. */
    private function payload(EventSetting $s): array
    {
        $d = $s->domain ?? [];
        $sub = $d['subdomain'] ?? null;
        $custom = $d['custom_domain'] ?? null;
        $status = $d['status'] ?? DomainService::STATUS_UNCONFIGURED;
        $token = $d['verification_token'] ?? null;

        return [
            'apex' => $this->domains->apex(),
            'cname_target' => (string) config('eventos.domain.cname_target'),
            'ingress_ip' => (string) config('eventos.domain.ip'),
            'subdomain' => $sub,
            'subdomain_url' => $sub ? "https://{$sub}.{$this->domains->apex()}" : null,
            'custom_domain' => $custom,
            'custom_domain_url' => $custom ? "https://{$custom}" : null,
            'status' => $status,
            'verified_at' => $d['verified_at'] ?? null,
            'checked_at' => $d['checked_at'] ?? null,
            'error' => $d['error'] ?? null,
            'dns_records' => ($custom && $token)
                ? $this->domains->dnsRecords($custom, $token)
                : [],
            'primary_url' => ($custom && $status === DomainService::STATUS_ACTIVE)
                ? "https://{$custom}"
                : ($sub ? "https://{$sub}.{$this->domains->apex()}" : null),
        ];
    }
}
