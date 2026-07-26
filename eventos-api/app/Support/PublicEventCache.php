<?php

namespace App\Support;

use App\Models\EventSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Redis keys for the hottest public microsite payloads. Short TTLs keep the
 * attendee SPA snappy; forget*() clears them the moment an organizer saves.
 */
final class PublicEventCache
{
    public const SITE_TTL = 120;

    public const RECEPTION_TTL = 60;

    public static function siteKey(string $identity): string
    {
        return 'public:site:'.strtolower($identity);
    }

    public static function receptionKey(string $identity): string
    {
        return 'public:reception:'.strtolower($identity);
    }

    /** Cache key segment for a verified custom hostname. */
    public static function hostIdentity(string $host): string
    {
        return 'host:'.strtolower(trim($host));
    }

    public static function forgetSubdomain(?string $subdomain): void
    {
        $sub = is_string($subdomain) ? strtolower(trim($subdomain)) : '';
        if ($sub === '') {
            return;
        }

        Cache::forget(self::siteKey($sub));
        Cache::forget(self::receptionKey($sub));
    }

    public static function forgetHost(?string $host): void
    {
        $h = is_string($host) ? strtolower(trim($host)) : '';
        if ($h === '') {
            return;
        }

        $id = self::hostIdentity($h);
        Cache::forget(self::siteKey($id));
        Cache::forget(self::receptionKey($id));
    }

    public static function forgetEvent(int $eventId): void
    {
        $setting = EventSetting::on('pgsql_admin')->where('event_id', $eventId)->first();
        $domain = $setting?->domain ?? [];
        self::forgetSubdomain(data_get($domain, 'subdomain'));
        self::forgetHost(data_get($domain, 'custom_domain'));
    }
}
