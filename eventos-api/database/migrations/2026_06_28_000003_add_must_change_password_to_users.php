<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flags a login whose password was reset by an admin and should be changed on
 * next sign-in (the exhibitor "Reset password → ask user to change…" option).
 * Stored on the user; exposed via UserResource so the SPA can prompt for a
 * change. (Hard enforcement at the auth layer is a follow-up.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
