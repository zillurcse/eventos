<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the exhibitor-admin login email to exhibitors, unique per event
 * (case-insensitive, ignoring soft-deleted rows). Guarded so it is a no-op on
 * fresh installs where the create-migration already adds the column + index.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exhibitors') && ! Schema::hasColumn('exhibitors', 'email')) {
            Schema::table('exhibitors', function (Blueprint $table) {
                $table->string('email', 180)->nullable()->after('slug');
            });
        }

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_exhibitor_event_email ON exhibitors (event_id, lower(email)) WHERE email IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_exhibitor_event_email');

        if (Schema::hasTable('exhibitors') && Schema::hasColumn('exhibitors', 'email')) {
            Schema::table('exhibitors', fn (Blueprint $table) => $table->dropColumn('email'));
        }
    }
};
