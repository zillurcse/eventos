<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Form builder — the dynamic backbone (architecture §6.12). Registration,
 * speaker, partner forms and surveys are all `forms`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                   // public render token
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete(); // null = org template
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60)->default('custom');     // registration|speaker|partner|survey|custom
            $table->string('target_entity', 30)->nullable();  // contact|participation|partner|survey
            $table->string('name', 180);
            $table->string('description', 500)->nullable();
            $table->string('status', 20)->default('draft');   // draft|published|closed
            $table->integer('version')->default(1);           // bumped on structural change
            $table->jsonb('settings')->nullable();            // submit limits, redirect, theme
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('form_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180)->nullable();
            $table->string('description', 500)->nullable();
            $table->integer('sort_order')->default(0);
        });

        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('form_sections')->nullOnDelete();
            $table->string('key', 60);                        // maps into profile_data JSON key
            $table->string('label', 180)->nullable();
            $table->string('help_text', 500)->nullable();
            $table->string('type', 30)->default('text');      // text|textarea|email|phone|select|multiselect|checkbox|radio|date|file|rating|section_break
            $table->boolean('is_default')->default(false);    // seeded core field vs organizer-added
            $table->boolean('is_required')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->boolean('is_pii')->default(false);
            $table->jsonb('validation')->nullable();          // min/max/regex/accepted types
            $table->jsonb('default_value')->nullable();
            $table->integer('sort_order')->default(0);
            $table->index(['form_id', 'sort_order']);
        });

        Schema::create('form_field_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('form_fields')->cascadeOnDelete();
            $table->string('label', 180);
            $table->string('value', 180)->nullable();
            $table->integer('sort_order')->default(0);
        });

        Schema::create('form_logic_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('when_field_id')->constrained('form_fields')->cascadeOnDelete();
            $table->string('operator', 20);                   // equals|not_equals|contains|gt|lt|filled
            $table->jsonb('value')->nullable();
            $table->string('action', 20);                     // show|hide|require|optional|jump
            $table->foreignId('target_field_id')->nullable()->constrained('form_fields')->nullOnDelete();
            $table->foreignId('target_section_id')->nullable()->constrained('form_sections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_logic_rules');
        Schema::dropIfExists('form_field_options');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('form_sections');
        Schema::dropIfExists('forms');
    }
};
