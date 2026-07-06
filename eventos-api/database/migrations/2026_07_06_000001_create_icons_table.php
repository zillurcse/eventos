<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global icon catalog used by icon-picker fields across the admin (e.g. the
 * event Content Hub "Participate Profile" cards). `key` maps to a client-side
 * SVG registry entry — the DB only curates which keys are selectable and how
 * they're labelled/ordered/searched, it does not store path data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icons', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();      // matches a frontend Icon registry entry, e.g. "users"
            $table->string('label', 80);              // display name, e.g. "Users"
            $table->string('category', 60)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icons');
    }
};
