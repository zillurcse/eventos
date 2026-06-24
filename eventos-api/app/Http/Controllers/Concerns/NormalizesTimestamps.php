<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Carbon;

/**
 * Normalizes ISO-8601 datetime inputs (which may carry a zone offset) to UTC
 * before storage, so the absolute instant is preserved (architecture §6.3.1).
 *
 * Eloquent formats a parsed Carbon in its own zone without converting, so an
 * input like "18:00+04:00" would otherwise be stored as 18:00Z instead of 14:00Z.
 */
trait NormalizesTimestamps
{
    protected function utcDates(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $data[$key] = Carbon::parse($data[$key])->utc();
            }
        }

        return $data;
    }
}
