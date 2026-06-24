<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * GDPR erasure (architecture §1.1): hard-deletes a contact and the PII that
 * hangs off them. Runs on the migrator connection (BYPASSRLS) since erasure is
 * a cross-cutting admin action; FK cascades remove participations and their
 * children (tickets are nulled, session_speaker/feed_reactions/etc. cascade).
 */
class PurgeContact extends Command
{
    protected $signature = 'gdpr:purge {email} {--org= : restrict to one organization id} {--force}';

    protected $description = 'GDPR erasure: permanently delete a contact and their personal data.';

    public function handle(): int
    {
        $contact = Contact::on('pgsql_admin')
            ->withTrashed()
            ->where('email', $this->argument('email'))
            ->when($this->option('org'), fn ($q, $org) => $q->where('organization_id', $org))
            ->first();

        if (! $contact) {
            $this->error("No contact found for {$this->argument('email')}.");

            return self::FAILURE;
        }

        if (! $this->option('force')
            && ! $this->confirm("Permanently erase {$contact->email} (contact #{$contact->id}) and ALL related personal data?")) {
            $this->comment('Aborted.');

            return self::SUCCESS;
        }

        DB::connection('pgsql_admin')->transaction(function () use ($contact) {
            $userId = $contact->user_id;

            // Cascades remove participations + their children via FK constraints.
            $contact->forceDelete();

            // Remove the login if it isn't a platform/org staff account.
            if ($userId) {
                User::on('pgsql_admin')
                    ->where('id', $userId)
                    ->where('is_platform_staff', false)
                    ->forceDelete();
            }
        });

        $this->info("Erased {$contact->email} and related personal data.");

        return self::SUCCESS;
    }
}
