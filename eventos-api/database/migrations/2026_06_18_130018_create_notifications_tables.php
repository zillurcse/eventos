<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notification system (architecture §6.7) — multi-channel delivery with
 * per-user preferences and device push tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete(); // NULL = platform
            $table->string('key', 120);                         // meeting.requested
            $table->string('channel', 20)->default('in_app');   // email|push|sms|in_app
            $table->string('locale', 10)->nullable();
            $table->string('subject', 255)->nullable();         // handlebars-style vars
            $table->text('body')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'key', 'channel', 'locale'], 'uq_notif_template');
            $table->foreign('locale')->references('code')->on('locales')->nullOnDelete();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->morphs('notifiable');                        // user | contact | participation
            $table->string('channel', 20)->default('in_app');
            $table->string('template_key', 120)->nullable();
            $table->string('title', 255)->nullable();
            $table->text('body')->nullable();
            $table->jsonb('data')->nullable();                   // deep-link payload
            $table->string('status', 20)->default('queued');     // queued|sent|delivered|failed|read
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('created_at')->nullable();
        });
        DB::statement("CREATE INDEX idx_notif_status ON notifications (status, created_at) WHERE status IN ('queued','failed')");

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('category', 60)->default('all');      // meetings|feed|announcements
            $table->boolean('email')->default(true);
            $table->boolean('push')->default(true);
            $table->boolean('sms')->default(false);
            $table->boolean('in_app')->default(true);
            $table->timestampsTz();
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20)->default('web');      // ios|android|web
            $table->string('token', 255)->unique();              // FCM/APNs token
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_templates');
    }
};
