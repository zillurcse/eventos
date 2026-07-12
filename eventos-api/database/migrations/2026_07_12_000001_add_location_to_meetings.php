<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a one-to-one meeting physically happens ("Hall 4", "Meeting Room 2").
 * Only meaningful for venue/hybrid events — an online event has no floor to
 * meet on — so the API requires it for those formats and leaves it NULL for
 * online ones. The organizer can pre-define the places attendees may pick from
 * (event_settings.meeting.locations); the column stores the resolved label so a
 * later edit to that list never rewrites history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('location', 180)->nullable()->after('agenda');
        });

        Schema::table('exhibitor_meeting_requests', function (Blueprint $table) {
            $table->string('location', 180)->nullable()->after('agenda');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('location');
        });

        Schema::table('exhibitor_meeting_requests', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
