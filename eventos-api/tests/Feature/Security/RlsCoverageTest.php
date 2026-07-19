<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guard test for the app-wide RLS hardening (audit finding M-3).
 *
 * Introspects the live Postgres catalog — no hard-coded table list — so a NEW
 * tenant table added later without proper RLS fails CI. For every base or
 * partitioned-parent table in `public` carrying an organization_id column
 * (partition children excluded, matching the migration), it asserts:
 *   1. ENABLE + FORCE row level security (pg_class.relrowsecurity/relforcerowsecurity);
 *   2. a `tenant_isolation` policy exists (pg_policies);
 *   3. the policy's WITH CHECK has NO "IS NULL" branch — no tenant table permits
 *      an org-NULL write. The USING/qual side MAY carry IS NULL (shared-row
 *      reads), so we deliberately do not assert against qual.
 *
 * Read-only; no fixtures. Requires Postgres — skips (never false-passes) on any
 * other driver.
 */
class RlsCoverageTest extends TestCase
{
    public function test_every_tenant_table_has_forced_rls_with_hardened_write_check(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            $this->markTestSkipped(
                "RLS coverage can only be introspected on Postgres (pg_catalog); the active driver is '{$driver}'."
            );
        }

        // Same table set the hardening migration operates on: every base or
        // partitioned-parent table with an organization_id column, partition
        // children (…_default) excluded via relispartition = false.
        $rows = DB::select(<<<'SQL'
            SELECT c.table_name,
                   pc.relrowsecurity      AS enabled,
                   pc.relforcerowsecurity AS forced,
                   p.policyname,
                   p.with_check
            FROM information_schema.columns c
            JOIN pg_class pc    ON pc.relname = c.table_name
            JOIN pg_namespace n ON n.oid = pc.relnamespace AND n.nspname = 'public'
            LEFT JOIN pg_policies p
                   ON p.schemaname = 'public'
                  AND p.tablename  = c.table_name
                  AND p.policyname = 'tenant_isolation'
            WHERE c.table_schema = 'public'
              AND c.column_name  = 'organization_id'
              AND pc.relkind IN ('r', 'p')     -- ordinary tables + partitioned parents
              AND pc.relispartition = false    -- skip partition children (…_default)
            ORDER BY c.table_name
        SQL);

        $this->assertNotEmpty(
            $rows,
            'No organization_id-bearing tables found in schema public — is the database migrated?'
        );

        $failures = [];

        foreach ($rows as $row) {
            if (! $row->enabled || ! $row->forced) {
                $failures[] = "{$row->table_name}: missing ENABLE/FORCE ROW LEVEL SECURITY";

                continue;
            }

            if ($row->policyname === null) {
                $failures[] = "{$row->table_name}: no `tenant_isolation` policy";

                continue;
            }

            if ($row->with_check === null) {
                // A policy with no WITH CHECK falls back to USING for writes, and
                // USING carries the IS NULL shared-row branch — an org-NULL write
                // would slip through. Treat as a violation.
                $failures[] = "{$row->table_name}: `tenant_isolation` has no WITH CHECK (writes fall back to USING, which permits org-NULL)";

                continue;
            }

            // NULLIF(...) legitimately contains "NULL" but never the token
            // "IS NULL", so matching on "IS NULL" only flags a real NULL branch.
            if (stripos($row->with_check, 'IS NULL') !== false) {
                $failures[] = "{$row->table_name}: WITH CHECK contains \"IS NULL\" (org-NULL write permitted) — {$row->with_check}";
            }
        }

        $this->assertSame(
            [],
            $failures,
            "RLS coverage violations (M-3) — each tenant table must be FORCE-RLS'd with a ".
            "tenant_isolation policy whose WITH CHECK has no IS NULL branch:\n  - ".
            implode("\n  - ", $failures)
        );
    }
}
