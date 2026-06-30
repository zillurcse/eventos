<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Badge designs for the badge.expouse canvas editor. The column set mirrors the
 * original EXPOUSE `badges` table (badge_json / font_json / back_json, format,
 * is_default, measurements_type, width/height as strings, bg_color/bg_image,
 * padding_*, badge_for, layers) so designs round-trip 1:1 — adapted to EventOS
 * multi-tenant conventions: an extra organization_id (RLS) + updated_by, and
 * named `badge_designs` to avoid clashing with the existing check-in `badges`
 * table. RLS via the same tenant_isolation policy used across the schema (§4.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->json('badge_json')->nullable();
            $table->json('font_json')->nullable();
            $table->json('back_json')->nullable();
            $table->string('format')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('measurements_type')->nullable();
            $table->string('width')->nullable();
            $table->string('height')->nullable();
            $table->string('bg_color')->nullable();
            $table->string('bg_image')->nullable();
            $table->string('padding_top')->nullable();
            $table->string('padding_right')->nullable();
            $table->string('padding_bottom')->nullable();
            $table->string('padding_left')->nullable();
            $table->string('badge_for')->nullable()->comment('attendee, speakers etc');
            $table->json('layers')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('event_id');
        });

        // RLS backstop — same tenant_isolation policy as the rest of the schema.
        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        DB::statement('ALTER TABLE badge_designs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE badge_designs FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON badge_designs');
        DB::statement(
            "CREATE POLICY tenant_isolation ON badge_designs ".
            "USING {$predicate} WITH CHECK {$predicate}"
        );
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON badge_designs');
        Schema::dropIfExists('badge_designs');
    }
};
