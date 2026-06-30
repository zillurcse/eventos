<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Communication → Functionality config (event_settings.communication):
 *   {
 *     functionality: { <operation>: { attendee, speaker, exhibitor, sponsor } },
 *     moderation:    { agenda_question, create_post, create_polls },
 *     feed_tabs:     [ { key, label, enabled } ]   // ordered
 *   }
 * Controls which user type may perform each feed/agenda operation, which
 * entries are moderated, and which feed tabs are shown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->jsonb('communication')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn('communication');
        });
    }
};
