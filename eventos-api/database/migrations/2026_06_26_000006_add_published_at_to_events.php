<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records when an event was first made public (Content Hub → Publishing). Set on
 * publish, cleared on unpublish.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestampTz('published_at')->nullable()->after('status');
        });

        // Back-fill already-published events so the dashboard shows a sensible date.
        DB::statement("UPDATE events SET published_at = updated_at WHERE status = 'published' AND published_at IS NULL");
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
