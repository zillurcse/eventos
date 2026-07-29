<?php

namespace App\Support;

use App\Models\EventSetting;
use App\Models\Meeting;

/**
 * Communication → Meetings › Intelligent Meeting.
 *
 * When enabled, the event app auto-generates bookable slots from the organizer's
 * slot duration, assigns an attendee table when a meeting is confirmed, and
 * exposes a meeting-area map of table occupancy.
 */
class IntelligentMeeting
{
    /** @return list<array{id: string, name: string, capacity: int, design: string, image_url: string|null, accent: string|null}> */
    public static function attendeeTables(int $eventId): array
    {
        $lounge = self::loungeConfig($eventId);
        if (empty($lounge['attendee_tables_enabled'])) {
            return [];
        }

        $out = [];
        foreach ((array) ($lounge['attendee_tables'] ?? []) as $t) {
            if (! is_array($t) || empty($t['id'])) {
                continue;
            }
            $design = in_array($t['design'] ?? null, ['round', 'boardroom', 'lounge'], true)
                ? $t['design']
                : 'round';
            $out[] = [
                'id' => 'att_'.$t['id'],
                'name' => trim((string) ($t['name'] ?? '')) ?: 'Table',
                'capacity' => max(1, (int) ($t['capacity'] ?? 4)),
                'design' => $design,
                'image_url' => $t['image_url'] ?? null,
                'accent' => $t['accent'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Table ids already booked for a lounge slot (requested or confirmed meetings).
     *
     * @return list<string>
     */
    public static function bookedTableIds(
        int $eventId,
        string $date,
        string $slot,
        ?int $excludeMeetingId = null,
    ): array {
        $query = Meeting::where('event_id', $eventId)
            ->whereIn('status', ['requested', 'confirmed'])
            ->where('meta->lounge_date', $date)
            ->where('meta->lounge_slot', $slot)
            ->whereNotNull('meta->allocated_table_id');

        if ($excludeMeetingId) {
            $query->where('id', '!=', $excludeMeetingId);
        }

        return $query
            ->get(['meta'])
            ->map(fn (Meeting $m) => (string) ($m->meta['allocated_table_id'] ?? ''))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Pick the first free attendee table for a slot, or null when none remain.
     *
     * @return array{id: string, name: string, capacity: int, design: string, image_url: string|null, accent: string|null}|null
     */
    public static function allocateTable(
        int $eventId,
        string $date,
        string $slot,
        ?int $excludeMeetingId = null,
    ): ?array {
        $taken = self::bookedTableIds($eventId, $date, $slot, $excludeMeetingId);

        foreach (self::attendeeTables($eventId) as $table) {
            if (! in_array($table['id'], $taken, true)) {
                return $table;
            }
        }

        return null;
    }

    /**
     * Upcoming bookings per attendee table — powers the meeting area map.
     *
     * @return list<array{
     *   id: string,
     *   name: string,
     *   capacity: int,
     *   design: string,
     *   image_url: string|null,
     *   accent: string|null,
     *   bookings: list<array{date: string, slot: string, status: string}>
     * }>
     */
    public static function areaMap(int $eventId): array
    {
        $tables = self::attendeeTables($eventId);
        if ($tables === []) {
            return [];
        }

        $bookingsByTable = [];
        foreach ($tables as $t) {
            $bookingsByTable[$t['id']] = [];
        }

        $meetings = Meeting::where('event_id', $eventId)
            ->whereIn('status', ['requested', 'confirmed'])
            ->whereNotNull('meta')
            ->get(['status', 'meta']);

        foreach ($meetings as $m) {
            $tableId = $m->meta['allocated_table_id'] ?? null;
            $date = $m->meta['lounge_date'] ?? null;
            $slot = $m->meta['lounge_slot'] ?? null;
            if (! $tableId || ! $date || ! $slot || ! isset($bookingsByTable[$tableId])) {
                continue;
            }
            $bookingsByTable[$tableId][] = [
                'date' => $date,
                'slot' => $slot,
                'status' => $m->status,
            ];
        }

        return array_map(fn (array $t) => [
            ...$t,
            'bookings' => $bookingsByTable[$t['id']] ?? [],
        ], $tables);
    }

    private static function loungeConfig(int $eventId): array
    {
        $setting = EventSetting::where('event_id', $eventId)->first();
        $lounge = $setting?->lounge;

        return is_array($lounge) ? $lounge : [];
    }
}
