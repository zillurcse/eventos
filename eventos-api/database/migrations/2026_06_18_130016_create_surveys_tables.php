<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surveys & live polls (architecture §6.6). Questions are form_fields; a
 * response is a form_submission — no parallel question/answer model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('form_id')->constrained();        // the questions
            $table->string('title', 200);
            $table->string('type', 20)->default('survey');      // survey | live_poll
            $table->boolean('is_anonymous')->default(false);
            $table->string('status', 20)->default('draft');
            $table->timestampTz('opens_at')->nullable();
            $table->timestampTz('closes_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->nullable()->constrained()->nullOnDelete(); // null = anon
            $table->foreignId('submission_id')->nullable()->constrained('form_submissions')->nullOnDelete(); // the answers
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('surveys');
    }
};
