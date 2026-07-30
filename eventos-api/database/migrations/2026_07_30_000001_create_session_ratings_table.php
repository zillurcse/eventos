<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_ratings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->timestampTz('rated_at')->nullable();
            $table->timestampsTz();

            $table->unique(['session_id', 'participation_id']);
            $table->index(['event_id', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_ratings');
    }
};
