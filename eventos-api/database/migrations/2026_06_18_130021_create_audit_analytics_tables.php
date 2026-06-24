<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit, activity, security & analytics (architecture §6.9, §6.11, §11).
 * audit_logs and analytics_events are RANGE-partitioned by month; old
 * partitions can be detached/archived to keep the hot set small. A DEFAULT
 * partition catches everything until the scheduler provisions monthly ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── audit_logs (immutable, append-only, partitioned by created_at) ──
        DB::statement(<<<'SQL'
            CREATE TABLE audit_logs (
                id              bigint GENERATED ALWAYS AS IDENTITY,
                organization_id bigint,
                actor_type      varchar(255),
                actor_id        bigint,
                event           varchar(60),
                auditable_type  varchar(255),
                auditable_id    bigint,
                old_values      jsonb,
                new_values      jsonb,
                ip_address      varchar(45),
                user_agent      text,
                created_at      timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
        SQL);
        DB::statement('CREATE TABLE audit_logs_default PARTITION OF audit_logs DEFAULT');
        DB::statement('CREATE INDEX idx_audit_org_time ON audit_logs (organization_id, created_at)');
        DB::statement('CREATE INDEX idx_audit_subject ON audit_logs (auditable_type, auditable_id)');

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('subject_type', 255)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type', 255)->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->jsonb('properties')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);                          // login_failed|mfa|token_revoked
            $table->string('ip_address', 45)->nullable();
            $table->jsonb('details')->nullable();
            $table->timestampTz('created_at')->nullable();
        });

        // ── analytics_events (high-volume facts, partitioned by occurred_at) ──
        DB::statement(<<<'SQL'
            CREATE TABLE analytics_events (
                id              bigint GENERATED ALWAYS AS IDENTITY,
                organization_id bigint,
                event_id        bigint,
                type            varchar(60),
                actor_type      varchar(255),
                actor_id        bigint,
                subject_type    varchar(255),
                subject_id      bigint,
                properties      jsonb,
                occurred_at     timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (id, occurred_at)
            ) PARTITION BY RANGE (occurred_at)
        SQL);
        DB::statement('CREATE TABLE analytics_events_default PARTITION OF analytics_events DEFAULT');
        DB::statement('CREATE INDEX idx_ae_org_type_time ON analytics_events (organization_id, type, occurred_at)');

        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('metric', 60);                        // attendance|engagement|revenue
            $table->string('dimension', 60)->nullable();         // by_session|by_day|by_track
            $table->timestampTz('period_start')->nullable();
            $table->timestampTz('period_end')->nullable();
            $table->jsonb('data')->nullable();                   // pre-aggregated series
            $table->timestampTz('generated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
        DB::statement('DROP TABLE IF EXISTS analytics_events');
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('activity_logs');
        DB::statement('DROP TABLE IF EXISTS audit_logs');
    }
};
