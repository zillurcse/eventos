<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Check-in & badges (architecture §6.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_in_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('location', 180)->nullable();
            $table->string('type', 20)->default('entrance');   // entrance | session | booth
            $table->timestampsTz();
        });

        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('station_id')->nullable()->constrained('check_in_stations')->nullOnDelete();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 10)->default('in');    // in | out
            $table->timestampTz('scanned_at')->nullable();

            $table->index(['participation_id', 'scanned_at']);
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('qr_token', 120)->unique();
            $table->unsignedBigInteger('file_id')->nullable()->index(); // soft ref → files (rendered PDF)
            $table->timestampTz('printed_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badges');
        Schema::dropIfExists('check_ins');
        Schema::dropIfExists('check_in_stations');
    }
};
