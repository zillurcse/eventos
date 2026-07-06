<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content Hub sections not yet backed by a column:
 * - event_highlights: array of { id, name, icon, count }
 * - participant_profiles: array of { id, name, icon, active }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->jsonb('event_highlights')->nullable();
            $table->jsonb('participant_profiles')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn(['event_highlights', 'participant_profiles']);
        });
    }
};
