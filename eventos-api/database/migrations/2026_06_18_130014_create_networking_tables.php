<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Networking & meetings (architecture §6.5). A meeting is one-to-one OR group:
 * any number of participations up to max_participants join via the pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_participation_id')->constrained('participations')->cascadeOnDelete();
            $table->foreignId('addressee_participation_id')->constrained('participations')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');  // pending|accepted|declined|blocked
            $table->string('message', 500)->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['event_id', 'requester_participation_id', 'addressee_participation_id'],
                'uq_connection'
            );
            $table->index(['addressee_participation_id', 'status'], 'idx_connections_addressee');
        });

        Schema::create('availability_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('is_booked')->default(false);
            $table->string('location_hint', 120)->nullable();  // booth/room/online
            $table->timestampsTz();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_participation_id')->constrained('participations')->cascadeOnDelete();
            $table->string('title', 200)->nullable();
            $table->string('agenda', 1000)->nullable();
            $table->string('type', 20)->default('one_on_one'); // one_on_one | group
            $table->integer('max_participants')->nullable();   // NULL = unlimited (group)
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('meeting_url', 500)->nullable();
            $table->string('status', 20)->default('requested'); // requested|confirmed|declined|canceled|completed
            $table->foreignId('slot_id')->nullable()->constrained('availability_slots')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['organizer_participation_id', 'starts_at'], 'idx_meetings_organizer');
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('guest');      // host | guest
            $table->string('rsvp', 20)->default('pending');    // accepted | declined | pending
            $table->timestampTz('joined_at')->nullable();

            $table->primary(['meeting_id', 'participation_id']);
            $table->index('participation_id', 'idx_meeting_parts_partic');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('availability_slots');
        Schema::dropIfExists('connections');
    }
};
