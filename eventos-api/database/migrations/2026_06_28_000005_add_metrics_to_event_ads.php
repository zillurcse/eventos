<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalized impression/click counters for the AD Managements "Insights"
 * dashboard. Bumped via the ad track endpoint when an ad is shown/clicked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_ads', function (Blueprint $table) {
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('event_ads', function (Blueprint $table) {
            $table->dropColumn(['impressions', 'clicks']);
        });
    }
};
