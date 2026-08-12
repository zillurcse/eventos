<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Booth access switch for exhibitor team members. A deactivated member keeps
 * their row — leads, scans and assignments stay attributed to them — but loses
 * access to the booth until an exhibitor admin switches them back on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhibitor_members', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('role'); // active | inactive
        });
    }

    public function down(): void
    {
        Schema::table('exhibitor_members', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
