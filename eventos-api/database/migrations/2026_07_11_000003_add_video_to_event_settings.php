<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event video-provider credentials (Settings › Video).
 *
 * Jitsi-hosted sessions need a signing key so the API can issue the host a
 * moderator JWT — without one, a public Jitsi parks attendees on "waiting for
 * a moderator" and the room never starts. Organizers bring their own JaaS
 * account, so the credentials belong on the event, not in the platform's .env
 * (which stays as the fallback).
 *
 * The private key is stored encrypted (Laravel Crypt) and never sent back to
 * the client — the API only reports whether one is present.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->jsonb('video')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn('video');
        });
    }
};
