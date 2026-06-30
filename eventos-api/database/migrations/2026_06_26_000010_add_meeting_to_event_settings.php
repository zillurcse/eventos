<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event meeting config (Communication → Meetings):
 *   {
 *     permissions: { from_role => { attendee, speaker, exhibitor, sponsor } },
 *     intelligent: bool,
 *     slot_duration: 10|15|30,
 *     restrictions: { role => { requests, confirmed } }
 *   }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->jsonb('meeting')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn('meeting');
        });
    }
};
