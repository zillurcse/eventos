<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Event;
use App\Models\Exhibitor;
use App\Models\ExhibitorMember;
use App\Models\ExhibitorPackage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Exhibitor data seeder. Two jobs, both idempotent:
 *
 *  1. Backfill — every existing exhibitor missing an email gets a generated
 *     login email + an exhibitor-admin login with the password "12345678"
 *     (now that email is required to create one).
 *  2. Demo — events that have an exhibitor package but no exhibitors yet get a
 *     few sample exhibitors, each with the same "12345678" login.
 *
 * Runs on the `pgsql_admin` connection so it bypasses RLS; organization_id is
 * set explicitly on every write (the tenant GUC isn't set in a seeder).
 *
 *   php artisan db:seed --class=ExhibitorSeeder --database=pgsql_admin
 */
class ExhibitorSeeder extends Seeder
{
    private const CONN = 'pgsql_admin';
    private const PASSWORD = '12345678';

    public function run(): void
    {
        $this->backfillMissingEmails();
        $this->seedDemoExhibitors();
    }

    /** Give every email-less exhibitor a login email + a "12345678" password. */
    private function backfillMissingEmails(): void
    {
        $missing = Exhibitor::on(self::CONN)
            ->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))
            ->get();

        $count = 0;
        foreach ($missing as $exhibitor) {
            // Deterministic + globally unique (slug is per-event, id is global).
            $email = "{$exhibitor->slug}-{$exhibitor->id}@exhibitor.eventos.test";

            $exhibitor->forceFill(['email' => $email])->save();
            $this->provisionLogin($exhibitor, $email);
            $count++;
        }

        $this->command?->info("Backfilled {$count} exhibitor(s) with an email + \"".self::PASSWORD.'" login.');
    }

    /** Seed a few demo exhibitors for events that have a package but no booths. */
    private function seedDemoExhibitors(): void
    {
        $samples = [
            ['type' => 'exhibitor', 'name' => 'Altus Group'],
            ['type' => 'exhibitor', 'name' => 'DarGlobal'],
            ['type' => 'sponsor',   'name' => 'MoHUP'],
        ];

        $created = 0;
        $events = Event::on(self::CONN)->get();

        foreach ($events as $event) {
            $package = ExhibitorPackage::on(self::CONN)->where('event_id', $event->id)->first();
            if (! $package) {
                continue; // package_id is required — skip events without one.
            }

            $hasAny = Exhibitor::on(self::CONN)->where('event_id', $event->id)->exists();
            if ($hasAny) {
                continue; // don't pollute events that already have exhibitors.
            }

            foreach ($samples as $s) {
                $slug = $this->uniqueSlug($s['name'], $event->id);
                $email = "{$slug}-{$event->id}@exhibitor.eventos.test";

                $exhibitor = Exhibitor::on(self::CONN)->create([
                    'organization_id' => $event->organization_id,
                    'event_id' => $event->id,
                    'type' => $s['type'],
                    'name' => $s['name'],
                    'slug' => $slug,
                    'email' => $email,
                    'package_id' => $package->id,
                    'tier_rank' => 0,
                    'status' => 'active',
                ]);

                $this->provisionLogin($exhibitor, $email);
                $created++;
            }
        }

        $this->command?->info("Seeded {$created} demo exhibitor(s) across empty events.");
    }

    /**
     * Create/refresh the exhibitor-admin login (Contact → User → admin member)
     * with the fixed seed password. Mirrors ExhibitorController::provisionAdmin
     * but sets a known password and sends no email.
     */
    private function provisionLogin(Exhibitor $exhibitor, string $email): void
    {
        $contact = Contact::on(self::CONN)->firstOrCreate(
            ['organization_id' => $exhibitor->organization_id, 'email' => $email],
            ['first_name' => $exhibitor->name],
        );

        if ($contact->user_id) {
            $user = User::on(self::CONN)->find($contact->user_id);
            $user?->forceFill(['password' => self::PASSWORD])->save();
        } else {
            $user = new User;
            $user->setConnection(self::CONN);
            $user->forceFill([
                'name' => $exhibitor->name,
                'email' => $email,
                'password' => self::PASSWORD,      // hashed by the model cast
                'email_verified_at' => now(),
                'status' => 'active',
            ])->save();
            $contact->forceFill(['user_id' => $user->id])->save();
        }

        ExhibitorMember::on(self::CONN)->updateOrCreate(
            ['exhibitor_id' => $exhibitor->id, 'contact_id' => $contact->id],
            ['role' => 'admin'],
        );

        $exhibitor->forceFill(['admin_contact_id' => $contact->id])->save();
    }

    private function uniqueSlug(string $name, int $eventId): string
    {
        $base = Str::slug($name) ?: 'exhibitor';
        $slug = $base;
        $i = 1;
        while (Exhibitor::on(self::CONN)->where('event_id', $eventId)->where('slug', $slug)->withTrashed()->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
