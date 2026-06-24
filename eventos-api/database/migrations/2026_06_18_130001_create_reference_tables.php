<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reference / seed data with no tenant scope (architecture §6.1, §6.2, §6.10).
 * locales, features (gateable catalog), permissions (atomic RBAC permissions).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locales', function (Blueprint $table) {
            $table->string('code', 10)->primary();          // en, bn, es...
            $table->string('name', 80);
            $table->string('native_name', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('direction', 3)->default('ltr');  // ltr | rtl
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();            // module.networking
            $table->string('name', 120);
            $table->string('type', 20)->default('boolean');  // boolean | quota | metered
            $table->string('description', 255)->nullable();
            $table->timestampsTz();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();            // attendees.manage
            $table->string('group', 60)->nullable();         // module grouping
            $table->string('description', 255)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('features');
        Schema::dropIfExists('locales');
    }
};
