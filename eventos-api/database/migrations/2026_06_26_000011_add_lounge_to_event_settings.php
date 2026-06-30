<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event networking lounge config (Communication → Lounge):
 *   {
 *     enabled: bool,
 *     slots_open_all: bool,
 *     slots: { "YYYY-MM-DD": ["10:00-10:30", ...] },
 *     attendee_tables_enabled: bool,
 *     attendee_tables: [ { id, name, capacity, image_file_id, image_url } ],
 *     exhibitor_tables_enabled: bool,
 *     exhibitor_default_meetings: int,
 *     exhibitor_meetings: { "<partner-uuid>": int },
 *     exhibitor_order: [ "<partner-uuid>" ],
 *     sponsor_tables_enabled: bool,
 *     sponsor_default_meetings: int,
 *     sponsor_meetings: { "<partner-uuid>": int },
 *     sponsor_order: [ "<partner-uuid>" ]
 *   }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->jsonb('lounge')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn('lounge');
        });
    }
};
