<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC + user↔organization membership (architecture §6.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete(); // NULL = platform role
            $table->string('name', 80);                    // owner, manager, staff, super_admin
            $table->string('scope', 20)->default('tenant'); // platform | tenant
            $table->boolean('is_system')->default(false);   // protected, non-deletable
            $table->string('description', 255)->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'name']);
        });
        // Platform roles (org NULL) must still have unique names.
        DB::statement('CREATE UNIQUE INDEX uq_platform_role_name ON roles (name) WHERE organization_id IS NULL');

        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('invited'); // invited | active | suspended
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('invited_at')->nullable();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['user_id', 'organization_id']);  // one membership per pair
        });

        Schema::create('membership_role', function (Blueprint $table) {
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['membership_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_role');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('roles');
    }
};
