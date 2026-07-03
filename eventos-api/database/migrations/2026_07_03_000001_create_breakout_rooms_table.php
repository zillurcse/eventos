<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Breakout Rooms (Event Engagement › Breakout Rooms). Virtual/collaborative
 * rooms attendees join during an event — distinct from the physical `rooms`
 * (venue) table. v1 ships the core "Room Details" the organizer fills in;
 * moderation toggles, collaboration flags and analytics counters live in the
 * `meta` JSONB so the enterprise feature-set (see docs/breakout-rooms-
 * architecture.md) can grow in without a schema rewrite.
 *
 * Multi-tenant conventions mirror event_ads (§4.3): BelongsToOrganization +
 * Postgres RLS on the app.current_organization GUC, soft deletes, audit cols.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('breakout_rooms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // ── Room Details ──────────────────────────────────────────────
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('purpose', 20)->default('single');    // single | multiple  (Single vs Multiple Sessions)
            $table->string('type', 40)->default('workshop');     // workshop|networking|round_table|sponsor_demo|team|private|vip|interview|panel|ama|custom

            // Who can join: anyone (open) | coded (access code) | hidden (unlisted, link/invite only)
            $table->string('access_type', 20)->default('anyone');
            $table->string('access_code', 60)->nullable();

            $table->unsignedInteger('capacity')->nullable();
            $table->string('poster_url', 2000)->nullable();      // Session Poster Image

            // ── Media / integration (roadmap; safe defaults for v1) ───────
            $table->string('provider', 30)->default('webrtc');   // webrtc|zoom|teams|meet|jitsi|bbb|external
            $table->string('meeting_url', 2000)->nullable();     // external provider join URL
            $table->boolean('recording_enabled')->default(false);

            // draft (default) → published → archived
            $table->string('status', 20)->default('draft');
            $table->timestampTz('published_at')->nullable();

            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();

            // moderation, collaboration toggles, role overrides, analytics counters
            $table->jsonb('meta')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'starts_at']);
        });

        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        DB::statement('ALTER TABLE breakout_rooms ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE breakout_rooms FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON breakout_rooms');
        DB::statement(
            'CREATE POLICY tenant_isolation ON breakout_rooms '.
            "USING {$predicate} WITH CHECK {$predicate}"
        );
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON breakout_rooms');
        Schema::dropIfExists('breakout_rooms');
    }
};
