<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gates Scanning (Onsite): stations gain a meta jsonb for staffing config
 * (staff / kiosk counts) shown on the organizer gate table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_in_stations', function (Blueprint $table) {
            $table->jsonb('meta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('check_in_stations', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
