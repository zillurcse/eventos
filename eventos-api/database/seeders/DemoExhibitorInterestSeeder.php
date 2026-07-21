<?php

namespace Database\Seeders;

use App\Models\CheckIn;
use App\Models\CheckInStation;
use App\Models\Exhibitor;
use App\Models\ExhibitorConversation;
use App\Models\ExhibitorMeetingRequest;
use App\Models\ExhibitorMessage;
use App\Models\ExhibitorProduct;
use App\Models\Participation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo interest signals for Recommended Leads.
 *
 * The recommendation engine only ever reads real attendee behaviour, so a fresh
 * demo database shows an empty queue. This seeder manufactures that behaviour —
 * booth messages, meeting requests, saved booths, booth badge scans and stated
 * interests — spread across an event's attendees so the ranking, the reason
 * chips and the interaction timeline all have something true to show.
 *
 * Idempotent per exhibitor: a booth that already has conversations is skipped.
 *
 *   php artisan db:seed --class=DemoExhibitorInterestSeeder --database=pgsql_admin
 */
class DemoExhibitorInterestSeeder extends Seeder
{
    private const CONN = 'pgsql_admin';

    /** Booth vocabulary, matched against the attendee interests seeded below. */
    private const TAGS = ['logistics automation', 'warehouse robotics', 'fleet tracking', 'cold chain'];

    /**
     * One behaviour profile per attendee slot, hottest first. The mix is the
     * point: the top of the queue should be people who asked for something, the
     * tail should be people who merely look like a fit.
     */
    private const PROFILES = [
        ['messages' => 2, 'meeting' => true,  'bookmark' => true,  'visits' => 2, 'interests' => ['logistics automation', 'fleet tracking'], 'hours' => 3],
        ['messages' => 1, 'meeting' => true,  'bookmark' => false, 'visits' => 1, 'interests' => ['cold chain'],                             'hours' => 9],
        ['messages' => 3, 'meeting' => false, 'bookmark' => true,  'visits' => 0, 'interests' => ['warehouse robotics'],                     'hours' => 26],
        ['messages' => 0, 'meeting' => false, 'bookmark' => true,  'visits' => 3, 'interests' => ['logistics automation'],                   'hours' => 30],
        ['messages' => 1, 'meeting' => false, 'bookmark' => false, 'visits' => 0, 'interests' => [],                                         'hours' => 52],
        ['messages' => 0, 'meeting' => false, 'bookmark' => false, 'visits' => 1, 'interests' => ['fleet tracking'],                         'hours' => 74],
        ['messages' => 0, 'meeting' => false, 'bookmark' => false, 'visits' => 0, 'interests' => ['cold chain', 'warehouse robotics'],        'hours' => 96],
    ];

    private const OPENERS = [
        'Hi — we run 40 vehicles out of two depots. Does your platform handle multi-site routing?',
        'Could you share pricing for a mid-size fleet? Reviewing suppliers this quarter.',
        'Saw your demo on the stand this morning. Who handles integrations?',
        'We are replacing a legacy WMS in Q1. Is there an API for stock movements?',
    ];

    public function run(): void
    {
        $exhibitors = Exhibitor::on(self::CONN)->where('type', 'exhibitor')->get();

        $touched = 0;
        foreach ($exhibitors as $exhibitor) {
            $seeded = ExhibitorConversation::on(self::CONN)->where('exhibitor_id', $exhibitor->id)->exists();
            if ($seeded) {
                continue;
            }

            $attendees = Participation::on(self::CONN)
                ->where('event_id', $exhibitor->event_id)
                ->where('role', 'attendee')
                ->orderBy('id')
                ->limit(count(self::PROFILES))
                ->get();

            if ($attendees->isEmpty()) {
                continue;
            }

            $this->giveBoothVocabulary($exhibitor);
            $booth = $this->boothStation($exhibitor);

            foreach ($attendees as $i => $attendee) {
                $this->applyProfile($exhibitor, $attendee, self::PROFILES[$i % count(self::PROFILES)], $booth);
            }

            $touched++;
        }

        $this->command?->info("Seeded interest signals for {$touched} exhibitor(s).");
    }

    /** Tags + products are what a profile match is scored against. */
    private function giveBoothVocabulary(Exhibitor $exhibitor): void
    {
        $profile = $exhibitor->profile_data ?? [];

        if (empty($profile['tags'])) {
            $profile['tags'] = self::TAGS;
            $exhibitor->setConnection(self::CONN);
            $exhibitor->forceFill(['profile_data' => $profile])->save();
        }

        if (! ExhibitorProduct::on(self::CONN)->where('exhibitor_id', $exhibitor->id)->exists()) {
            $product = new ExhibitorProduct;
            $product->setConnection(self::CONN);
            $product->forceFill([
                'exhibitor_id' => $exhibitor->id,
                'name' => 'Fleet tracking',
                'description' => 'Live vehicle telemetry and route optimisation.',
            ])->save();
        }
    }

    /** The booth's scan point — reused if the organizer already created one. */
    private function boothStation(Exhibitor $exhibitor): CheckInStation
    {
        $station = CheckInStation::on(self::CONN)
            ->where('event_id', $exhibitor->event_id)
            ->where('type', 'booth')
            ->whereRaw("meta->>'exhibitor_id' = ?", [(string) $exhibitor->id])
            ->first();

        if ($station) {
            return $station;
        }

        $station = new CheckInStation;
        $station->setConnection(self::CONN);
        $station->forceFill([
            'event_id' => $exhibitor->event_id,
            'organization_id' => $exhibitor->organization_id,
            'name' => 'Booth '.Str::upper(Str::substr($exhibitor->slug ?: 'demo', 0, 4)),
            'type' => 'booth',
            'meta' => [
                'exhibitor_id' => $exhibitor->id,
                'exhibitor_name' => $exhibitor->name,
                'scan_mode' => 'staff',
                'lead_generation' => true,
            ],
        ])->save();

        return $station;
    }

    private function applyProfile(Exhibitor $exhibitor, Participation $attendee, array $profile, CheckInStation $booth): void
    {
        $at = now()->subHours($profile['hours']);

        if ($profile['interests']) {
            $data = $attendee->profile_data ?? [];
            $data['interests'] = array_values(array_unique([...(array) ($data['interests'] ?? []), ...$profile['interests']]));
            $attendee->setConnection(self::CONN);
            $attendee->forceFill(['profile_data' => $data])->save();
        }

        if ($profile['bookmark']) {
            $meta = $attendee->meta ?? [];
            $saved = (array) ($meta['bookmarks']['exhibitor'] ?? []);
            $meta['bookmarks']['exhibitor'] = array_values(array_unique([...$saved, $exhibitor->uuid]));
            $attendee->setConnection(self::CONN);
            $attendee->forceFill(['meta' => $meta])->save();
        }

        if ($profile['messages'] > 0) {
            $convo = new ExhibitorConversation;
            $convo->setConnection(self::CONN);
            $convo->forceFill([
                'organization_id' => $exhibitor->organization_id,
                'event_id' => $exhibitor->event_id,
                'exhibitor_id' => $exhibitor->id,
                'participation_id' => $attendee->id,
                'last_message_at' => $at,
                'created_at' => $at,
                'updated_at' => $at,
            ])->save();

            for ($n = 0; $n < $profile['messages']; $n++) {
                $sentAt = $at->copy()->addMinutes($n * 12);
                $message = new ExhibitorMessage;
                $message->setConnection(self::CONN);
                $message->forceFill([
                    'organization_id' => $exhibitor->organization_id,
                    'event_id' => $exhibitor->event_id,
                    'conversation_id' => $convo->id,
                    'sender_side' => 'attendee',
                    'sender_participation_id' => $attendee->id,
                    'body' => self::OPENERS[($attendee->id + $n) % count(self::OPENERS)],
                    'created_at' => $sentAt,
                    'updated_at' => $sentAt,
                ])->save();
            }
        }

        if ($profile['meeting']) {
            $request = new ExhibitorMeetingRequest;
            $request->setConnection(self::CONN);
            $request->forceFill([
                'organization_id' => $exhibitor->organization_id,
                'event_id' => $exhibitor->event_id,
                'exhibitor_id' => $exhibitor->id,
                'participation_id' => $attendee->id,
                'status' => 'requested',
                'subject' => 'Supplier review — 20 minutes',
                'agenda' => 'Looking at replacing our current provider before the new contract year.',
                'created_at' => $at,
                'updated_at' => $at,
            ])->save();
        }

        for ($n = 0; $n < $profile['visits']; $n++) {
            $scan = new CheckIn;
            $scan->setConnection(self::CONN);
            $scan->forceFill([
                'event_id' => $exhibitor->event_id,
                'organization_id' => $exhibitor->organization_id,
                'participation_id' => $attendee->id,
                'station_id' => $booth->id,
                'direction' => 'in',
                // Return visits are earlier passes, not future ones.
                'scanned_at' => $at->copy()->subHours($n * 5),
            ])->save();
        }
    }
}
