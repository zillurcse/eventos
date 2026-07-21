<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Exhibitor;
use App\Models\ExhibitorLead;
use App\Models\ExhibitorMember;
use Illuminate\Database\Seeder;

/**
 * Demo lead-capture data. For every exhibitor (type = exhibitor) that has no
 * leads yet, seeds a prospect list spread across the booth's team and across
 * the last week, so both the Leads CRM and Team Connections have something
 * realistic to show (owners, unassigned walk-ups, shared accounts, a trend).
 *
 * Runs on pgsql_admin (bypasses RLS); organization_id is set explicitly.
 *
 *   php artisan db:seed --class=DemoExhibitorLeadsSeeder --database=pgsql_admin
 */
class DemoExhibitorLeadsSeeder extends Seeder
{
    private const CONN = 'pgsql_admin';

    /**
     * `rep` indexes into the booth's team; null leaves the lead unassigned.
     * `days` is how many days ago it was captured.
     */
    private const SAMPLES = [
        ['name' => 'Sarah Whitfield',  'company' => 'Brightline Logistics',  'rating' => 'hot',  'status' => 'won',       'rep' => 0,    'days' => 6, 'source' => 'scan',    'notes' => 'Signed at the booth — onboarding call booked.'],
        ['name' => 'David Okonkwo',    'company' => 'Vertex Manufacturing',  'rating' => 'warm', 'status' => 'contacted', 'rep' => 0,    'days' => 5, 'source' => 'scan',    'notes' => 'Interested in bulk pricing, sent brochure.'],
        ['name' => 'Emily Carter',     'company' => 'Northgate Properties',  'rating' => 'warm', 'status' => 'connected', 'rep' => 1,    'days' => 5, 'source' => 'scan',    'notes' => 'Early stage, gathering information.'],
        ['name' => 'Rajesh Menon',     'company' => 'Lumen Systems',         'rating' => 'hot',  'status' => 'qualified', 'rep' => 1,    'days' => 4, 'source' => 'connect', 'notes' => 'Budget confirmed for next quarter.'],
        ['name' => 'Anna Kowalski',    'company' => 'EuroBuild Group',       'rating' => 'hot',  'status' => 'connected', 'rep' => 2,    'days' => 3, 'source' => 'scan',    'notes' => 'Strong fit, decision maker on site.'],
        ['name' => 'Michael Tan',      'company' => 'Skyline Elevators',     'rating' => 'cold', 'status' => 'contacted', 'rep' => 2,    'days' => 2, 'source' => 'manual',  'notes' => 'Asked about maintenance contracts.'],
        ['name' => 'Fatima Al-Rashid', 'company' => 'Gulf Construction Co.', 'rating' => 'cold', 'status' => 'pending',   'rep' => null, 'days' => 2, 'source' => 'scan',    'notes' => null],
        ['name' => 'Tomas Lindqvist',  'company' => 'Brightline Logistics',  'rating' => 'warm', 'status' => 'contacted', 'rep' => 1,    'days' => 1, 'source' => 'connect', 'notes' => 'Second contact at Brightline — procurement side.'],
        ['name' => 'Grace Mensah',     'company' => 'Harbour Freight Ltd',   'rating' => 'hot',  'status' => 'qualified', 'rep' => 0,    'days' => 1, 'source' => 'scan',    'notes' => 'Wants a quote for two sites.'],
        ['name' => 'Julien Roche',     'company' => 'Atlas Retail Partners', 'rating' => 'cold', 'status' => 'lost',      'rep' => 2,    'days' => 1, 'source' => 'scan',    'notes' => 'Went with an incumbent supplier.'],
        ['name' => 'Priya Nair',       'company' => 'Meridian Health',       'rating' => 'warm', 'status' => 'connected', 'rep' => 0,    'days' => 0, 'source' => 'scan',    'notes' => 'Booth demo today, follow up Friday.'],
        ['name' => 'Owen Bradley',     'company' => 'Northgate Properties',  'rating' => 'hot',  'status' => 'contacted', 'rep' => 2,    'days' => 0, 'source' => 'manual',  'notes' => 'Facilities lead — overlaps with Emily\'s contact.'],
        ['name' => 'Chen Wei',         'company' => 'Pacific Rail Systems',  'rating' => 'cold', 'status' => 'pending',   'rep' => null, 'days' => 0, 'source' => 'scan',    'notes' => null],
    ];

    /** Stand-in teammates so single-member booths still demo the team view. */
    private const DEMO_TEAM = [
        ['first_name' => 'Marcus', 'last_name' => 'Feld',   'role' => 'staff'],
        ['first_name' => 'Leila',  'last_name' => 'Haddad', 'role' => 'staff'],
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

            $team = $this->team($exhibitor);
            $slug = $exhibitor->slug ?: 'demo';

            foreach (self::SAMPLES as $i => $s) {
                // Wrap around so a two-person booth still gets a spread.
                $rep = $s['rep'] === null || $team->isEmpty() ? null : $team[$s['rep'] % $team->count()];
                $scannedAt = now()->subDays($s['days'])->setTime(10 + ($i % 8), ($i * 7) % 60);

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
                    'source' => $s['source'],
                    'notes' => $s['notes'],
                    'scanned_at' => $scannedAt,
                    'created_at' => $scannedAt,
                    'updated_at' => $scannedAt,
                ])->save();
                $created++;
            }
        }

        $this->command?->info("Seeded {$created} demo lead(s).");
    }

    /**
     * The booth's lead capturers, topped up with demo teammates so there is
     * always more than one owner to attribute connections to.
     */
    private function team(Exhibitor $exhibitor)
    {
        $members = ExhibitorMember::on(self::CONN)
            ->where('exhibitor_id', $exhibitor->id)
            ->orderBy('id')
            ->get();

        foreach (self::DEMO_TEAM as $i => $person) {
            if ($members->count() >= count(self::DEMO_TEAM) + 1) {
                break;
            }

            $email = 'demo.rep.'.($i + 1).'@'.($exhibitor->slug ?: 'demo').'-demo.test';

            $contact = Contact::on(self::CONN)->firstOrNew(['email' => $email]);
            $contact->setConnection(self::CONN);
            $contact->forceFill([
                'organization_id' => $exhibitor->organization_id,
                'first_name' => $person['first_name'],
                'last_name' => $person['last_name'],
                'company' => $exhibitor->name,
            ])->save();

            $member = ExhibitorMember::on(self::CONN)
                ->firstOrNew(['exhibitor_id' => $exhibitor->id, 'contact_id' => $contact->id]);
            $member->setConnection(self::CONN);
            $member->forceFill(['role' => $person['role'], 'is_lead_capturer' => true])->save();

            $members->push($member);
        }

        return $members->values();
    }
}
