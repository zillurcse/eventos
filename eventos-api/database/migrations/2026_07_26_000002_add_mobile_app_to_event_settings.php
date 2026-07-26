<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile app configuration on event_settings:
 *   manage_tabs  — ordered nav tabs { key, label, enabled }
 *   branded_app  — app identity object { enabled, app_name, tagline, icon_*, splash_*, primary_color, ios_url, android_url }
 *   app_banner   — in-app promotional banners [{ id, title, description, image_*, link_url, active }]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->jsonb('manage_tabs')->nullable();
            $table->jsonb('branded_app')->nullable();
            $table->jsonb('app_banner')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn(['manage_tabs', 'branded_app', 'app_banner']);
        });
    }
};
