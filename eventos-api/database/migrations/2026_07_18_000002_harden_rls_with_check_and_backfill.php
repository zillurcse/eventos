<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hardens Row-Level Security app-wide (audit finding M-3). Does NOT edit the
 * already-applied 2026_06_18_130099_enable_row_level_security.php.
 *
 * The original tenant_isolation policy used the same predicate for USING and
 * WITH CHECK: (organization_id IS NULL OR organization_id = <guc>). The IS NULL
 * branch in WITH CHECK let the app connection INSERT/UPDATE a tenant row with
 * organization_id = NULL, which then reads as a shared row visible to every
 * tenant. This recreates every tenant policy with IS NULL kept in USING only
 * (shared/platform rows stay readable) and dropped from WITH CHECK (every
 * tenant write must carry the current org).
 *
 * The table set is derived from the LIVE SCHEMA at migration time — every base
 * or partitioned-parent table in `public` carrying an organization_id column —
 * rather than a hand-typed list. This automatically covers tables added after
 * the original migration (chat_messages, session_polls, service_items, …) and
 * can never name a table that lacks the column, so CREATE POLICY cannot error
 * on a typo. Partition children (…_default) are skipped: the partitioned parent
 * carries the policy for all its partitions.
 *
 * M-2 note (false positive): coupons, refunds and security_events were reported
 * as tenant tables missing RLS, but the live schema shows none of them carry an
 * organization_id column (refunds isolates via payment_id, security_events via
 * user_id, coupons is global). They are correctly absent from the query below
 * and intentionally left untouched — an org-scoped policy cannot apply to them.
 *
 * GUC read matches the original migration and ResolveTenant exactly:
 *   ResolveTenant runs `SET app.current_organization = '<id>'`.
 *   NULLIF(current_setting('app.current_organization', true), '')::bigint
 *     - missing_ok = true → an unset GUC returns NULL, never raises;
 *     - NULLIF(…, '')     → an empty GUC collapses to NULL;
 *     - NULL then fails `organization_id = NULL` → rows invisible (deny).
 */
return new class extends Migration
{
    /** Matches the GUC name + cast the original migration and ResolveTenant use. */
    private string $guc = "NULLIF(current_setting('app.current_organization', true), '')::bigint";

    public function up(): void
    {
        // Hardened: no org-NULL escape hatch on writes.
        foreach ($this->tenantTables() as $table) {
            $this->reshape($table, "(organization_id = {$this->guc})");
        }
    }

    public function down(): void
    {
        // True inverse: restore the original shape — IS NULL permitted on BOTH
        // USING and WITH CHECK. Every table the query returns already had RLS
        // enabled before this migration (the original migration + each later
        // table's own create migration), so we only reshape the policy; nothing
        // is disabled. There is no "first-time enabled" group to tear down —
        // the false-positive backfill tables were never touched.
        foreach ($this->tenantTables() as $table) {
            $this->reshape($table, "(organization_id IS NULL OR organization_id = {$this->guc})");
        }
    }

    /**
     * Every ordinary or partitioned-parent table in `public` with an
     * organization_id column, excluding partition children (whose parent holds
     * the policy). Authoritative source of truth — read from the catalog.
     *
     * @return string[]
     */
    private function tenantTables(): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT c.table_name
            FROM information_schema.columns c
            JOIN pg_class pc ON pc.relname = c.table_name
            JOIN pg_namespace n ON n.oid = pc.relnamespace AND n.nspname = 'public'
            WHERE c.table_schema = 'public'
              AND c.column_name = 'organization_id'
              AND pc.relkind IN ('r', 'p')     -- ordinary tables + partitioned parents
              AND pc.relispartition = false    -- skip partition children (…_default)
            ORDER BY c.table_name
        SQL);

        return array_map(fn ($row) => $row->table_name, $rows);
    }

    private function reshape(string $table, string $writeCheck): void
    {
        // ENABLE + FORCE are idempotent: a no-op on tables already secured by an
        // earlier migration, and correct for any org table added since.
        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
        DB::statement(
            "CREATE POLICY tenant_isolation ON {$table} ".
            "USING (organization_id IS NULL OR organization_id = {$this->guc}) ".
            "WITH CHECK {$writeCheck}"
        );
    }
};
