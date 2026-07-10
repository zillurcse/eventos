<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contests (Event Engagement › Contests). Organizer-run attendee contests —
 * either an "entry" contest (attendees post a photo/video/selfie/article to
 * enter) or a "response" contest (attendees comment on an organizer post).
 * Mirrors the breakout_rooms conventions (§2026_07_03_000001): tenant
 * isolation via BelongsToOrganization + Postgres RLS, soft deletes, audit
 * columns, a `meta` JSONB catch-all for future fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // ── Contest Details ───────────────────────────────────────────
            $table->string('title', 200);
            $table->string('contest_type', 20)->default('entry'); // entry | response
            $table->text('description')->nullable();
            $table->string('description_file_url', 2000)->nullable();
            $table->string('description_file_name', 255)->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->string('banner_url', 2000)->nullable();
            $table->string('caption', 500)->nullable();

            // ── Post Details ──────────────────────────────────────────────
            $table->unsignedInteger('character_limit')->default(200);
            $table->unsignedInteger('points_for_entry')->default(10);
            $table->unsignedInteger('points_for_response')->default(10);
            $table->boolean('allow_photos')->default(true);
            $table->boolean('allow_videos')->default(false);
            $table->boolean('allow_selfie')->default(false);
            $table->string('winner_chooser', 20)->default('admin'); // admin | most_likes
            $table->unsignedInteger('winner_number')->default(3);
            $table->unsignedInteger('winning_points')->default(0);
            $table->boolean('equal_points_distribution')->default(false);

            // ── Settings ──────────────────────────────────────────────────
            $table->boolean('attach_mandatory')->default(false);
            $table->boolean('allow_multiple_entries')->default(false);
            $table->boolean('allow_moderate_entries')->default(false);
            $table->boolean('attendees_can_see_others_entries')->default(false);
            $table->boolean('attendees_can_see_other_comments')->default(false);

            // future settings, per-contest analytics counters, etc.
            $table->jsonb('meta')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['event_id', 'contest_type']);
            $table->index(['event_id', 'starts_at']);
        });

        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        DB::statement('ALTER TABLE contests ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE contests FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON contests');
        DB::statement(
            'CREATE POLICY tenant_isolation ON contests '.
            "USING {$predicate} WITH CHECK {$predicate}"
        );
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON contests');
        Schema::dropIfExists('contests');
    }
};
