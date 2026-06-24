<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessions/talks + speaker linkage (architecture §6.3, §10.4).
 * A speaker is a participation with role=speaker (no separate speakers table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 250);
            $table->text('description')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->string('timezone', 64)->nullable();        // IANA override; NULL = inherit event
            $table->integer('capacity')->nullable();
            $table->string('stream_url', 500)->nullable();     // online/hybrid
            $table->string('status', 20)->default('scheduled'); // scheduled|live|ended|canceled
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['event_id', 'starts_at']);
        });

        Schema::create('session_speaker', function (Blueprint $table) {
            $table->foreignId('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete(); // role=speaker
            $table->string('role', 40)->default('speaker');    // speaker|moderator|panelist|keynote
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();

            $table->primary(['session_id', 'participation_id']);
            $table->index('participation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_speaker');
        Schema::dropIfExists('sessions');
    }
};
