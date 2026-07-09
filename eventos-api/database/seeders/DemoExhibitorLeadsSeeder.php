<?php

namespace Database\Seeders;

use App\Models\Exhibitor;
use App\Models\ExhibitorLead;
use App\Models\ExhibitorMember;
use Illuminate\Database\Seeder;

/**
 * Demo lead-capture data. For every exhibitor (type = exhibitor) that has no
 * leads yet, seeds a small prospect list so the Leads CRM has something to show.
 *
 * Runs on pgsql_admin (bypasses RLS); organization_id is set explicitly.
 *
 *   php artisan db:seed --class=DemoExhibitorLeadsSeeder --database=pgsql_admin
 */
class DemoExhibitorLeadsSeeder extends Seeder
{
    private const CONN = 'pgsql_admin';

    private const SAMPLES = [
        ['name' => 'Sarah Whitfield',   'company' => 'Brightline Logistics',  'rating' => 'cold', 'status' => 'connected', 'notes' => 'Hot lead — requested a follow-up demo next week.'],
        ['name' => 'David Okonkwo',      'company' => 'Vertex Manufacturing',  'rating' => 'cold', 'status' => 'connected', 'notes' => 'Interested in bulk pricing, sent brochure.'],
        ['name' => 'Emily Carter',       'company' => 'Northgate Properties',  'rating' => 'warm', 'status' => 'connected', 'notes' => 'Early stage, gathering information.'],
        ['name' => 'Rajesh Menon',       'company' => 'Lumen Systems',         'rating' => 'hot',  'status' => 'pending',   'notes' => null],
        ['name' => 'Anna Kowalski',      'company' => 'EuroBuild Group',       'rating' => 'hot',  'status' => 'connected', 'notes' => 'Strong fit, decision maker on site.'],
        ['name' => 'Michael Tan',        'company' => 'Skyline Elevators',     'rating' => 'cold', 'status' => 'connected', 'notes' => 'Asked about maintenance contracts.'],
        ['name' => 'Fatima Al-Rashid',   'company' => 'Gulf Construction Co.', 'rating' => 'cold', 'status' => 'pending',   'notes' => null],
    ];

    public function run(): void
    {
        $exhibitors = Exhibitor::on(self::CONN)->where('type', 'exhibitor')->get();

        $created = 0;
        foreach ($exhibitors as $exhibitor) {
            $hasLeads = ExhibitorLead::on(self::CONN)->where('exhibitor_id', $exhibitor->id)->exists();
            if ($hasLeads) {
                continue;
            }

            $rep = ExhibitorMember::on(self::CONN)
                ->where('exhibitor_id', $exhibitor->id)
                ->orderBy('id')
                ->first();

            $slug = $exhibitor->slug ?: 'demo';
            $scannedAt = now()->setTime(15, 34);

            foreach (self::SAMPLES as $i => $s) {
                $lead = new ExhibitorLead;
                $lead->setConnection(self::CONN);
                $lead->forceFill([
                    'organization_id' => $exhibitor->organization_id,
                    'event_id' => $exhibitor->event_id,
                    'exhibitor_id' => $exhibitor->id,
                    'scanned_by_member_id' => $rep?->id,
                    'name' => $s['name'],
                    'email' => 'demo.lead.'.($i + 1).'@'.$slug.'-demo.test',
                    'company' => $s['company'],
                    'rating' => $s['rating'],
                    'status' => $s['status'],
                    'source' => 'scan',
                    'notes' => $s['notes'],
                    'scanned_at' => $scannedAt,
                ])->save();
                $created++;
            }
        }

        $this->command?->info("Seeded {$created} demo lead(s).");
    }
}
