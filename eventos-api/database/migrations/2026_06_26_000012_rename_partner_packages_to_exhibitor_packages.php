<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Renames partner_packages → exhibitor_packages on databases that were already
 * migrated under the old name. Guarded so it is a no-op on fresh installs,
 * where the create-migration already creates the table as exhibitor_packages.
 *
 * Postgres carries RLS policies and the partners.package_id foreign key across
 * a table rename automatically, so nothing else needs adjusting here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_packages') && ! Schema::hasTable('exhibitor_packages')) {
            Schema::rename('partner_packages', 'exhibitor_packages');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('exhibitor_packages') && ! Schema::hasTable('partner_packages')) {
            Schema::rename('exhibitor_packages', 'partner_packages');
        }
    }
};
