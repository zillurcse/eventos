<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lounge-slot bookings (Communication → Lounge). When an attendee books a
 * meeting into one of the organizer's configured lounge slots we record the
 * canonical, timezone-free slot key here so double-booking can be detected by
 * exact (date, slot) match regardless of the viewer's timezone:
 *   { lounge_date: "YYYY-MM-DD", lounge_slot: "HH:MM-HH:MM" }
 * starts_at / ends_at are still set (in the event timezone) for display.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->jsonb('meta')->nullable();
            // Fast conflict lookups: "is this (date, slot) already taken?".
            $table->index(['event_id', 'status'], 'idx_meetings_event_status');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropIndex('idx_meetings_event_status');
            $table->dropColumn('meta');
        });
    }
};
