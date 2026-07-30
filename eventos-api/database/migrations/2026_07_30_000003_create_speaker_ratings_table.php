<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speaker_ratings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('speaker_participation_id')->constrained('participations')->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->timestamp('rated_at')->nullable();
            $table->timestamps();

            $table->unique(['speaker_participation_id', 'participation_id']);
            $table->index(['event_id', 'speaker_participation_id']);
            $table->index(['organization_id', 'speaker_participation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speaker_ratings');
    }
};
