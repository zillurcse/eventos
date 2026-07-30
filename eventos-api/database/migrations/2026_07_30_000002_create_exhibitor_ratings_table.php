<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibitor_ratings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exhibitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->timestamp('rated_at')->nullable();
            $table->timestamps();

            $table->unique(['exhibitor_id', 'participation_id']);
            $table->index(['event_id', 'exhibitor_id']);
            $table->index(['organization_id', 'exhibitor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibitor_ratings');
    }
};
