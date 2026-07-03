<?php

namespace App\Services;

use App\Models\EventSetting;
use Illuminate\Support\Str;

/**
 * Per-event domain logic for the organizer Domain settings page.
 *
 * Two independent things:
 *   • Subdomain  — <sub>.<apex> (e.g. expo1234.eventos.app). Platform-managed:
 *     served by a wildcard DNS record + TLS cert on the edge, so the organizer
 *     needs no DNS of their own. We only validate format + global uniqueness.
 *   • Custom domain — events.company.com. Requires the organizer to prove
 *     ownership (TXT challenge) and point traffic at us (CNAME, or A for an
 *     apex). We verify both with live DNS lookups; the edge then issues TLS.
 *
 * State lives in event_settings.domain (JSONB); this service owns its shape.
 */
class DomainService
{
    /** @return array{status:string} the persisted domain sub-document defaults */
    public const STATUS_UNCONFIGURED = 'unconfigured';
    public const STATUS_PENDING = 'pending';   // saved, awaiting DNS
    public const STATUS_ACTIVE = 'active';     // DNS verified, live
    public const STATUS_FAILED = 'failed';     // last verification failed

    public function apex(): string
    {
        return (string) config('eventos.domain.apex');
    }

    public function reserved(): array
    {
        return (array) config('eventos.domain.reserved', []);
    }

    /** lowercase, trim, strip an accidental ".apex" suffix; return null if empty. */
    public function normalizeSubdomain(?string $value): ?string
    {
        $value = Str::of((string) $value)->lower()->trim()->value();
        if ($value === '') {
            return null;
        }
        $value = preg_replace('/\.'.preg_quote($this->apex(), '/').'$/', '', $value);

        return $value;
    }

    /** RFC-1123 label: a–z, 0–9, hyphens, not leading/trailing, 3–63 chars. */
    public function isValidSubdomain(string $sub): bool
    {
        return (bool) preg_match('/^(?!-)[a-z0-9-]{3,63}(?<!-)$/', $sub)
            && ! str_contains($sub, '--');
    }

    /** Globally unique across every tenant (checked on the unscoped admin conn). */
    public function isSubdomainTaken(string $sub, int $exceptEventId): bool
    {
        return EventSetting::on('pgsql_admin')
            ->where('domain->subdomain', $sub)
            ->where('event_id', '!=', $exceptEventId)
            ->exists();
    }

    /** lowercase FQDN, strip scheme/path/trailing dot. Return null if empty. */
    public function normalizeCustomDomain(?string $value): ?string
    {
        $value = Str::of((string) $value)->lower()->trim()->value();
        if ($value === '') {
            return null;
        }
        $value = preg_replace('#^https?://#', '', $value);
        $value = explode('/', $value)[0];

        return rtrim($value, '.');
    }

    public function isValidCustomDomain(string $domain): bool
    {
        if (str_ends_with($domain, '.'.$this->apex()) || $domain === $this->apex()) {
            return false; // must use the subdomain field for our own apex
        }

        return (bool) filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            && str_contains($domain, '.');
    }

    /** True when the domain is a bare apex (company.com), where CNAME is illegal. */
    public function isApexDomain(string $domain): bool
    {
        return substr_count($domain, '.') <= 1;
    }

    /** The DNS records the organizer must create for a custom domain. */
    public function dnsRecords(string $domain, string $token): array
    {
        $prefix = (string) config('eventos.domain.challenge_prefix');

        $routing = $this->isApexDomain($domain)
            ? ['type' => 'A', 'host' => '@', 'value' => (string) config('eventos.domain.ip'),
                'note' => 'Points your domain at the EventOS edge.']
            : ['type' => 'CNAME', 'host' => explode('.', $domain)[0], 'value' => (string) config('eventos.domain.cname_target'),
                'note' => 'Points your subdomain at the EventOS edge.'];

        return [
            $routing,
            ['type' => 'TXT', 'host' => $prefix.'.'.$domain, 'value' => 'eventos-verify='.$token,
                'note' => 'Proves you own this domain.'],
        ];
    }

    public function newToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Live DNS verification: TXT challenge present AND routing record points at us.
     *
     * @return array{ok:bool, ownership:bool, routing:bool, error:?string}
     */
    public function verify(string $domain, string $token): array
    {
        $prefix = (string) config('eventos.domain.challenge_prefix');
        $ownership = $this->txtContains($prefix.'.'.$domain, 'eventos-verify='.$token);

        $routing = $this->isApexDomain($domain)
            ? $this->aMatches($domain, (string) config('eventos.domain.ip'))
            : $this->cnameMatches($domain, (string) config('eventos.domain.cname_target'));

        $error = match (true) {
            ! $ownership && ! $routing => 'Neither the TXT nor the routing record was found yet. DNS can take a few minutes to propagate.',
            ! $ownership => 'The TXT ownership record was not found.',
            ! $routing => 'The routing (CNAME/A) record was not found or points elsewhere.',
            default => null,
        };

        return ['ok' => $ownership && $routing, 'ownership' => $ownership, 'routing' => $routing, 'error' => $error];
    }

    private function txtContains(string $host, string $needle): bool
    {
        foreach (@dns_get_record($host, DNS_TXT) ?: [] as $rec) {
            if (isset($rec['txt']) && trim($rec['txt']) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function cnameMatches(string $host, string $target): bool
    {
        $target = rtrim($target, '.');
        foreach (@dns_get_record($host, DNS_CNAME) ?: [] as $rec) {
            if (isset($rec['target']) && rtrim($rec['target'], '.') === $target) {
                return true;
            }
        }

        return false;
    }

    private function aMatches(string $host, string $ip): bool
    {
        foreach (@dns_get_record($host, DNS_A) ?: [] as $rec) {
            if (($rec['ip'] ?? null) === $ip) {
                return true;
            }
        }

        return false;
    }
}
