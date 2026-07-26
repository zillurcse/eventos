<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mail journeys — automated / manual emails configured under Mail › Emails.
 * Stored as an ordered array of journey objects (subject, from, template, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('event_settings', 'mail_emails')) {
            return;
        }

        Schema::table('event_settings', function (Blueprint $table) {
            $table->jsonb('mail_emails')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('event_settings', 'mail_emails')) {
            return;
        }

        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn('mail_emails');
        });
    }
};
