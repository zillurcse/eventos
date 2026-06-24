<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completed submissions + per-field values (architecture §6.12). Values are
 * normalized here and projected as JSONB onto the owning entity's profile_data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->integer('form_version')->default(1);       // snapshot of version submitted
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('owner');                   // contact|participation|partner|survey_response
            $table->foreignId('submitted_by_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('status', 20)->default('complete'); // complete | partial
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('form_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('form_submissions')->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('form_fields')->cascadeOnDelete();
            $table->jsonb('value')->nullable();                // typed value / option ids / file id
            $table->index('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_field_values');
        Schema::dropIfExists('form_submissions');
    }
};
