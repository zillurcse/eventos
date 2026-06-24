<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Event management core (architecture §6.3). All timestamps are UTC; the IANA
 * timezone is stored for convert-on-display (§6.3.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                   // public / QR
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('slug', 200);
            $table->text('description')->nullable();
            $table->string('format', 20)->default('venue');   // venue|online|hybrid
            $table->string('status', 20)->default('draft');   // draft|published|live|ended|archived
            $table->string('timezone', 64)->default('UTC');   // IANA, NOT NULL
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->unsignedBigInteger('primary_venue_id')->nullable()->index(); // soft ref → venues
            $table->unsignedBigInteger('cover_file_id')->nullable()->index();    // soft ref → files
            $table->string('default_locale', 10)->nullable();
            $table->integer('capacity')->nullable();
            $table->boolean('is_public')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('default_locale')->references('code')->on('locales')->nullOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX uq_event_slug ON events (organization_id, slug) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_events_org ON events (organization_id) WHERE deleted_at IS NULL');

        Schema::create('event_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->jsonb('branding')->nullable();
            $table->jsonb('theme')->nullable();
            $table->jsonb('modules_enabled')->nullable();      // per-event module toggles
            $table->jsonb('networking_config')->nullable();    // matchmaking rules
            $table->timestampTz('registration_open')->nullable();
            $table->timestampTz('registration_close')->nullable();
            $table->jsonb('privacy')->nullable();
            $table->timestampsTz();
        });

        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete(); // nullable = reusable
            $table->string('name', 200);
            $table->string('address', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('country', 120)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->unsignedBigInteger('map_file_id')->nullable()->index(); // soft ref → files
            $table->timestampsTz();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->integer('capacity')->nullable();
            $table->string('floor', 60)->nullable();
            $table->jsonb('meta')->nullable();                 // AV equipment, etc.
            $table->timestampsTz();
        });

        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('color', 20)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracks');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('venues');
        Schema::dropIfExists('event_settings');
        Schema::dropIfExists('events');
    }
};
