<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renames the whole "partner" concept → "exhibitor" on databases that were
 * already migrated under the old names. Guarded throughout so it is a safe
 * no-op on fresh installs (where the create-migration already uses the new
 * names). Postgres carries RLS policies, foreign keys and indexes across a
 * table rename automatically.
 */
return new class extends Migration
{
    /** old table => new table */
    private const TABLES = [
        'partners' => 'exhibitors',
        'partner_members' => 'exhibitor_members',
        'partner_documents' => 'exhibitor_documents',
        'partner_products' => 'exhibitor_products',
        'partner_projects' => 'exhibitor_projects',
    ];

    /** tables (new name) whose partner_id column becomes exhibitor_id */
    private const FK_TABLES = [
        'exhibitor_members', 'exhibitor_documents', 'exhibitor_products', 'exhibitor_projects', 'booths',
    ];

    public function up(): void
    {
        // 1. Tables
        foreach (self::TABLES as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }

        // 2. Foreign-key columns: partner_id → exhibitor_id
        foreach (self::FK_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'partner_id') && ! Schema::hasColumn($table, 'exhibitor_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->renameColumn('partner_id', 'exhibitor_id'));
            }
        }

        // 3. Indexes (names are global; ALTER INDEX IF EXISTS is a no-op when absent)
        DB::statement('ALTER INDEX IF EXISTS uq_partner_slug RENAME TO uq_exhibitor_slug');
        DB::statement('ALTER INDEX IF EXISTS idx_partners_event_type RENAME TO idx_exhibitors_event_type');

        // 4. RBAC permission key (pivot links by id, so the role grants survive)
        DB::table('permissions')->where('key', 'partners.manage')
            ->update(['key' => 'exhibitors.manage', 'group' => 'exhibitors']);

        // 5. Plan feature key (plan_features pivot links by id)
        DB::table('features')->where('key', 'module.partners')->update(['key' => 'module.exhibitors']);

        // 6. Form-builder entity descriptors + dynamic-field morph owner type
        DB::table('forms')->where('key', 'partner')->update(['key' => 'exhibitor']);
        DB::table('forms')->where('target_entity', 'partner')->update(['target_entity' => 'exhibitor']);
        DB::table('form_submissions')->where('owner_type', 'App\\Models\\Partner')
            ->update(['owner_type' => 'App\\Models\\Exhibitor']);

        // 7. Audit-trail morph type (audit_logs lives on the migrator connection)
        DB::connection('pgsql_admin')->table('audit_logs')->where('auditable_type', 'App\\Models\\Partner')
            ->update(['auditable_type' => 'App\\Models\\Exhibitor']);
    }

    public function down(): void
    {
        DB::table('form_submissions')->where('owner_type', 'App\\Models\\Exhibitor')
            ->update(['owner_type' => 'App\\Models\\Partner']);
        DB::table('forms')->where('target_entity', 'exhibitor')->update(['target_entity' => 'partner']);
        DB::table('forms')->where('key', 'exhibitor')->update(['key' => 'partner']);
        DB::table('features')->where('key', 'module.exhibitors')->update(['key' => 'module.partners']);
        DB::table('permissions')->where('key', 'exhibitors.manage')
            ->update(['key' => 'partners.manage', 'group' => 'partners']);

        DB::statement('ALTER INDEX IF EXISTS idx_exhibitors_event_type RENAME TO idx_partners_event_type');
        DB::statement('ALTER INDEX IF EXISTS uq_exhibitor_slug RENAME TO uq_partner_slug');

        foreach (self::FK_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'exhibitor_id') && ! Schema::hasColumn($table, 'partner_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->renameColumn('exhibitor_id', 'partner_id'));
            }
        }

        foreach (array_reverse(self::TABLES, true) as $old => $new) {
            if (Schema::hasTable($new) && ! Schema::hasTable($old)) {
                Schema::rename($new, $old);
            }
        }
    }
};
