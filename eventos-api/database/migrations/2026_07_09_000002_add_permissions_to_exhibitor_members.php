<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-member module ACL for exhibitor teams: which modules (products,
 * documents, projects, leads, meetings) a staff member may manage. Admins
 * implicitly have all; null = no extra access (defaults applied app-side).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhibitor_members', function (Blueprint $table) {
            $table->jsonb('permissions')->nullable()->after('is_lead_capturer');
        });
    }

    public function down(): void
    {
        Schema::table('exhibitor_members', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
