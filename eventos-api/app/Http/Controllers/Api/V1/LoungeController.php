<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSetting;
use App\Models\Exhibitor;
use App\Services\BreakoutRoom\Providers\LiveKitProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Networking-lounge tables (attendee-facing). The organizer configures tables
 * in Admin → Communication → Lounge (attendee tables) plus the event's
 * exhibitors/sponsors get a branded table each. Every table is a live LiveKit
 * room; attendees "Join us" to sit down over video. Occupancy is read live from
 * LiveKit so the seat visuals + the green "live" dot reflect who's actually
 * seated. Runs under the participant middleware (event org context is set).
 */
class LoungeController extends Controller
{
    /** Seats around an exhibitor/sponsor branded table (attendee tables set their own). */
    private const BRANDED_TABLE_SEATS = 8;

    public function __construct(private LiveKitProvider $livekit) {}

    /** GET /events/{event}/lounge/tables — the three tabs of lounge tables + live occupancy. */
    public function tables(Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $event = Event::findOrFail($eventId);
        $lounge = $this->loungeConfig($eventId);

        $tables = $this->buildTables($event, $lounge);
        $occupancy = $this->livekit->roomOccupancy();

        // Only pull the full seat roster for tables that actually have someone in
        // them (one ListParticipants RPC each), capped so a busy lounge can't fan
        // out into hundreds of calls. Empty tables need no roster.
        $rosterBudget = 40;

        $shape = function (array $t) use ($occupancy, &$rosterBudget) {
            $count = $occupancy[$t['room']] ?? 0;

            $occupants = [];
            if ($count > 0 && $rosterBudget > 0) {
                $rosterBudget--;
                $occupants = array_slice($this->livekit->participants($t['room']), 0, $t['capacity']);
            }
            $occupied = min(max($count, count($occupants)), $t['capacity']);

            return [
                'id' => $t['id'],
                'kind' => $t['kind'],
                'name' => $t['name'],
                'capacity' => $t['capacity'],
                'image_url' => $t['image_url'],
                'occupants' => $occupants,
                'occupied' => $occupied,
                'live' => $occupied > 0,
                'full' => $occupied >= $t['capacity'],
            ];
        };

        $group = fn (string $kind) => collect($tables)
            ->filter(fn ($t) => $t['kind'] === $kind)
            ->map($shape)
            ->values();

        return response()->json(['data' => [
            'enabled' => (bool) ($lounge['enabled'] ?? false),
            'tabs' => [
                'attendees' => $group('attendee'),
                'exhibitors' => $group('exhibitor'),
                'sponsors' => $group('sponsor'),
            ],
        ]]);
    }

    /** POST /events/{event}/lounge/tables/{table}/join — take a seat (mint a media token). */
    public function join(string $event, string $table, Request $request): JsonResponse
    {
        $eventId = $request->attributes->get('event_id');
        $eventModel = Event::findOrFail($eventId);
        $lounge = $this->loungeConfig($eventId);

        $found = collect($this->buildTables($eventModel, $lounge))->firstWhere('id', $table);
        abort_unless($found, 404, 'That lounge table no longer exists.');

        // Capacity guard against the live room (accurate single-room read).
        $seated = $this->livekit->participantCount($found['room']);
        if ($seated >= $found['capacity']) {
            throw ValidationException::withMessages(['table' => 'This table is full. Try another one.']);
        }

        $user = $request->user();
        $config = $this->livekit->joinConfigForRoom($found['room'], [
            // Identity uses the user UUID (what the client knows as `user.id`) so
            // the lounge UI can spot "your" seat among the live occupants.
            'identity' => 'user_'.($user?->uuid ?? 'guest'),
            'name' => $user?->name ?? 'Guest',
            'role' => 'attendee',
            'canPublish' => true,          // lounge tables are participatory (mic + camera)
            'avatar_url' => $request->input('avatar_url'),
        ]);

        return response()->json(['data' => $config + ['title' => $found['name']]]);
    }

    // ── internals ───────────────────────────────────────────────────────────

    private function loungeConfig(int $eventId): array
    {
        $s = EventSetting::where('event_id', $eventId)->first();

        return is_array($s?->lounge) ? $s->lounge : [];
    }

    /**
     * Flatten every lounge table into a uniform shape with a stable, tenant-safe
     * LiveKit room name. Used by both the list and the join capacity check so the
     * two never disagree on a table's identity or seat count.
     */
    private function buildTables(Event $event, array $lounge): array
    {
        $tables = [];
        $room = fn (string $key) => 'lounge_'.$event->uuid.'__'.$key;

        // Attendee tables — organizer-authored, with their own names + capacity.
        if (! empty($lounge['attendee_tables_enabled'])) {
            foreach ((array) ($lounge['attendee_tables'] ?? []) as $t) {
                if (! is_array($t) || empty($t['id'])) {
                    continue;
                }
                $id = 'att_'.$t['id'];
                $tables[] = [
                    'id' => $id,
                    'kind' => 'attendee',
                    'name' => $t['name'] ?: 'Table',
                    'capacity' => max(1, (int) ($t['capacity'] ?? 4)),
                    'image_url' => $t['image_url'] ?? null,
                    'room' => $room($id),
                ];
            }
        }

        // Exhibitor / sponsor branded tables — one per partner, ordered as configured.
        $tables = array_merge(
            $tables,
            $this->partnerTables($event, 'exhibitor', ! empty($lounge['exhibitor_tables_enabled']), (array) ($lounge['exhibitor_order'] ?? []), $room),
            $this->partnerTables($event, 'sponsor', ! empty($lounge['sponsor_tables_enabled']), (array) ($lounge['sponsor_order'] ?? []), $room),
        );

        return $tables;
    }

    private function partnerTables(Event $event, string $type, bool $enabled, array $order, callable $room): array
    {
        if (! $enabled) {
            return [];
        }

        $partners = Exhibitor::with('logoFile')
            ->where('event_id', $event->id)
            ->where('type', $type)
            ->get();

        // Honor the organizer's drag-order; unlisted partners fall to the end.
        $rank = array_flip($order);
        $partners = $partners->sortBy(fn ($p) => $rank[$p->uuid] ?? PHP_INT_MAX)->values();

        return $partners->map(function ($p) use ($type, $room) {
            $id = ($type === 'exhibitor' ? 'ex_' : 'sp_').$p->uuid;
            $logo = $p->logoFile;

            return [
                'id' => $id,
                'kind' => $type,
                'name' => $p->name,
                'capacity' => self::BRANDED_TABLE_SEATS,
                'image_url' => $logo ? Storage::disk($logo->disk)->url($logo->path) : null,
                'room' => $room($id),
            ];
        })->all();
    }
}
