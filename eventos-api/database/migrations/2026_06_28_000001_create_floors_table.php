<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Floor plans for the floor.expouse canvas editor, adapted to EventOS
 * multi-tenant conventions. A floor belongs to an event (and, for RLS, an
 * organization). The canvas state is stored as JSON: `dimensions`, `floor_area`,
 * `objects` (walls/booths/shapes), `dom_elements`, and `offset`. RLS is enabled
 * via the same tenant_isolation policy used across the schema (§4.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->json('dimensions')->nullable();
            $table->json('floor_area')->nullable();
            $table->string('shape_type')->default('rectangular');
            $table->json('objects')->nullable();
            $table->json('dom_elements')->nullable();
            $table->json('offset')->nullable();
            $table->integer('zoom')->default(1);
            $table->boolean('wall_generated')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('event_id');
        });

        // RLS backstop — same tenant_isolation policy as the rest of the schema.
        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        DB::statement('ALTER TABLE floors ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE floors FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON floors');
        DB::statement(
            "CREATE POLICY tenant_isolation ON floors ".
            "USING {$predicate} WITH CHECK {$predicate}"
        );
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON floors');
        Schema::dropIfExists('floors');
    }
};
