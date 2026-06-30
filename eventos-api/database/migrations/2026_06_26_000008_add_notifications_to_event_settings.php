<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event automatic-notification matrix (Communication → Notification):
 * { action_key => { web: bool, email: bool, sms: bool } }. Picks which channels
 * fire for each attendee-facing trigger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->jsonb('notifications')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn('notifications');
        });
    }
};
