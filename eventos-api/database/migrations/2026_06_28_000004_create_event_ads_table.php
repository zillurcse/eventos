<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Event advertisements (the "AD Managements" section), adapted from the EXPOUSE
 * `event_ads` table to EventOS multi-tenant conventions. Each ad belongs to a
 * placement (main = all pages, featured, content = sessions page); an event can
 * have up to a handful of ads per placement. The image carousel + per-image
 * redirect config, plus the audience targeting (groups & pages), are stored as
 * JSON. RLS via the standard tenant_isolation policy (§4.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('placement')->default('main');   // main | featured | content
            $table->string('title');
            $table->boolean('is_active')->default(true);

            // [{ image_url, redirect_type, redirect_target_id, redirect_target_label, is_active }]
            $table->json('images')->nullable();
            $table->json('targeted_groups')->nullable();     // [attendees, vip, speakers, …]
            $table->json('targeted_pages')->nullable();      // [reception, feed, sessions, …]

            $table->timestampTz('start_at')->nullable();
            $table->timestampTz('end_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['event_id', 'placement']);
        });

        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        DB::statement('ALTER TABLE event_ads ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE event_ads FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON event_ads');
        DB::statement(
            "CREATE POLICY tenant_isolation ON event_ads ".
            "USING {$predicate} WITH CHECK {$predicate}"
        );
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON event_ads');
        Schema::dropIfExists('event_ads');
    }
};
