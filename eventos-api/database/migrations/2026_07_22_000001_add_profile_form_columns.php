<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profile form builder (Event Settings › Profile).
 *
 * form_fields.meta — presentation/behaviour flags the builder edits per field:
 *   placeholder, width (50|100), visible (eye toggle), show_to_others
 *   (value appears on the attendee's public profile), surfaces
 *   {registration, onboarding, public} — which of the three collection
 *   surfaces the field appears on.
 *
 * form_submissions.source        — where a submission came from
 *                                  (link|embed|onboarding|registration|admin).
 * form_submissions.review_status — organizer review ladder
 *                                  (pending|approved|rejected).
 * form_submissions.meta          — submitter snapshot (name/email), ip, ua.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->jsonb('meta')->nullable();
        });

        Schema::table('form_submissions', function (Blueprint $table) {
            $table->string('source', 20)->default('link');
            $table->string('review_status', 20)->default('pending');
            $table->jsonb('meta')->nullable();
            $table->index(['form_id', 'review_status']);
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropIndex(['form_id', 'review_status']);
            $table->dropColumn(['source', 'review_status', 'meta']);
        });

        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
