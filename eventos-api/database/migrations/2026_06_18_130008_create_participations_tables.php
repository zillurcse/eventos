<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A contact's involvement in one event, with a role (architecture §6.4).
 * One person across many events = many participations, one contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                   // used in QR/badge
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('attendee');  // attendee|speaker|partner_member|staff
            $table->string('status', 20)->default('registered'); // registered|confirmed|checked_in|canceled|no_show
            $table->unsignedBigInteger('ticket_id')->nullable()->index();                  // soft ref → tickets
            $table->unsignedBigInteger('registration_submission_id')->nullable()->index(); // soft ref → form_submissions
            $table->jsonb('profile_data')->nullable();        // per-event builder projection
            $table->boolean('networking_opt_in')->default(false);
            $table->timestampTz('checked_in_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('contact_id');
        });
        DB::statement('CREATE UNIQUE INDEX uq_participation ON participations (event_id, contact_id, role) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_participations_event ON participations (event_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX gin_partic_profile ON participations USING GIN (profile_data)');

        Schema::create('participation_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('type', 30)->default('manual');    // manual | dynamic segment
            $table->jsonb('rules')->nullable();
            $table->timestampsTz();
        });

        Schema::create('participation_group_member', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained('participation_groups')->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete();
            $table->primary(['group_id', 'participation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_group_member');
        Schema::dropIfExists('participation_groups');
        Schema::dropIfExists('participations');
    }
};
