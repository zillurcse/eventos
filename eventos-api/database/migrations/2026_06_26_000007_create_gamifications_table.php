<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event gamification config (Communication → Gamification). A single row per
 * event: the on/off toggle, a { action_key => score } points map, and an award
 * block shown on the event login page. RLS uses the same tenant_isolation
 * policy as the rest of the schema (§4.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->jsonb('scores')->nullable();           // { action_key => int }
            $table->string('award_title', 255)->nullable();
            $table->longText('award_description')->nullable();
            $table->timestampsTz();
        });

        // RLS backstop — same tenant_isolation policy as the rest of the schema.
        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        DB::statement('ALTER TABLE gamifications ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE gamifications FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON gamifications');
        DB::statement(
            "CREATE POLICY tenant_isolation ON gamifications ".
            "USING {$predicate} WITH CHECK {$predicate}"
        );
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON gamifications');
        Schema::dropIfExists('gamifications');
    }
};
