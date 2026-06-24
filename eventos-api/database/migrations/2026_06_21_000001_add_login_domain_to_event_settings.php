<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event Login Setup + Domain config (organizer Event Settings).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->jsonb('login')->nullable();   // {methods:{password,magic_link,guest}, require_login}
            $table->jsonb('domain')->nullable();  // {subdomain, custom_domain}
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn(['login', 'domain']);
        });
    }
};
