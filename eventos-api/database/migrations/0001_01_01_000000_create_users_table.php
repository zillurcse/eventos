<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Global user accounts (platform staff + organizer members).
     * Org binding lives in `memberships`; this table is NOT tenant-scoped.
     * (architecture §6.1)
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 180);
            $table->string('email', 180);                    // → citext below
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password')->nullable();          // nullable for SSO-only
            $table->string('phone', 32)->nullable();         // E.164
            $table->unsignedBigInteger('avatar_file_id')->nullable()->index(); // soft ref → files
            $table->string('locale', 10)->default('en');
            $table->string('timezone', 64)->default('UTC');  // viewer's preferred zone
            $table->string('display_timezone_mode', 20)->default('event_local'); // event_local|viewer_local|manual
            $table->string('manual_timezone', 64)->nullable();
            $table->boolean('is_platform_staff')->default(false);
            $table->string('mfa_secret')->nullable();        // encrypted at app layer
            $table->timestampTz('last_login_at')->nullable();
            $table->string('status', 20)->default('active'); // active|disabled
            $table->jsonb('meta')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Case-insensitive, globally-unique email.
        DB::statement('ALTER TABLE users ALTER COLUMN email TYPE citext');
        DB::statement('CREATE UNIQUE INDEX uq_users_email ON users (email) WHERE deleted_at IS NULL');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        // NOTE: no `sessions` table here — sessions live in Redis (SESSION_DRIVER=redis),
        // and the name `sessions` is reserved for event talks (architecture §6.3).
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
