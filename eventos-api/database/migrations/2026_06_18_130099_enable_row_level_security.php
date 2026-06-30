<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Row-Level Security backstop (architecture §4.3, §11). Enables + FORCEs RLS on
 * every tenant-owned table and installs a tenant_isolation policy keyed on the
 * `app.current_organization` GUC set per request by ResolveTenant.
 *
 * Policy semantics:
 *   - rows with organization_id IS NULL are shared/platform rows (visible to all);
 *   - otherwise a row is visible only when its org matches the request's GUC;
 *   - if the GUC is unset, only shared rows are visible (safe default-deny).
 *
 * eventos_migrator has BYPASSRLS, so migrations/seeders are unaffected; the app
 * connects as eventos_app (no bypass) and is fully constrained.
 */
return new class extends Migration
{
    /** Every table that carries an organization_id discriminator. */
    private array $tenantTables = [
        'organization_settings', 'roles', 'memberships',
        'subscriptions', 'invoices', 'payments', 'usage_meters',
        'contacts',
        'events', 'event_settings', 'venues', 'rooms', 'tracks',
        'forms', 'participations', 'participation_groups', 'form_submissions',
        'sessions', 'exhibitor_packages', 'exhibitors', 'booths',
        'ticket_types', 'discount_codes', 'orders', 'tickets',
        'check_in_stations', 'check_ins', 'badges',
        'connections', 'availability_slots', 'meetings',
        'feed_posts', 'feed_comments', 'feed_reactions', 'announcements',
        'document_folders', 'documents', 'surveys', 'survey_responses',
        'email_blocks', 'email_templates', 'email_sends',
        'notification_templates', 'notifications', 'notification_preferences',
        'files', 'translations',
        'activity_logs', 'audit_logs', 'analytics_events', 'report_snapshots',
    ];

    public function up(): void
    {
        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        foreach ($this->tenantTables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement(
                "CREATE POLICY tenant_isolation ON {$table} ".
                "USING {$predicate} WITH CHECK {$predicate}"
            );
        }
    }

    public function down(): void
    {
        foreach ($this->tenantTables as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
