<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant root + per-tenant settings (architecture §6.1).
 * organizations is the tenant — it is NOT itself org-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 180);
            $table->string('slug', 180);
            $table->string('status', 20)->default('pending'); // active|suspended|pending|archived
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('default_locale', 10)->nullable();
            $table->string('default_timezone', 64)->default('UTC');
            $table->char('default_currency', 3)->default('USD');
            $table->string('billing_email', 180)->nullable();
            $table->string('data_region', 20)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('default_locale')->references('code')->on('locales')->nullOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX uq_org_slug ON organizations (slug) WHERE deleted_at IS NULL');

        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('branding')->nullable();              // logo, colors, custom domain
            $table->jsonb('feature_overrides')->nullable();     // manual feature toggles
            $table->jsonb('notification_defaults')->nullable(); // default channels
            $table->jsonb('security')->nullable();              // SSO, MFA policy, IP allowlist
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
        Schema::dropIfExists('organizations');
    }
};
