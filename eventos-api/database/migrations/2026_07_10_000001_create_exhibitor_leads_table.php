<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exhibitor lead capture (§6.3). A "lead" is a prospect the booth collected —
 * scanned from a badge, added manually, or promoted from an attendee who
 * connected with the booth. The exhibitor CRM lets the team rate it
 * (hot/warm/cold), track a pipeline status, attribute it to the rep who
 * captured it, and jot notes.
 *
 * Multi-tenant conventions mirror the exhibitor_* tables: BelongsToOrganization
 * + Postgres RLS on the app.current_organization GUC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibitor_leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exhibitor_id')->constrained('exhibitors')->cascadeOnDelete();

            // The attendee this lead came from, when captured inside the event.
            $table->foreignId('participation_id')->nullable()->constrained('participations')->nullOnDelete();
            // The teammate who scanned / created the lead.
            $table->foreignId('scanned_by_member_id')->nullable()->constrained('exhibitor_members')->nullOnDelete();

            // Denormalised prospect card (may be an off-platform contact).
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();

            $table->string('rating', 10)->default('cold');   // hot | warm | cold
            $table->string('status', 20)->default('pending'); // pending | connected | contacted | qualified | won | lost
            $table->string('source', 20)->default('manual');  // scan | manual | connect | import
            $table->text('notes')->nullable();

            $table->timestampTz('scanned_at')->nullable();
            $table->timestampTz('exported_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['exhibitor_id', 'rating']);
            $table->index(['exhibitor_id', 'status']);
            $table->index(['exhibitor_id', 'scanned_by_member_id']);
            $table->unique(['exhibitor_id', 'participation_id'], 'uq_exhibitor_lead_participation');
        });

        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        DB::statement('ALTER TABLE exhibitor_leads ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE exhibitor_leads FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON exhibitor_leads');
        DB::statement(
            'CREATE POLICY tenant_isolation ON exhibitor_leads '.
            "USING {$predicate} WITH CHECK {$predicate}"
        );
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON exhibitor_leads');
        Schema::dropIfExists('exhibitor_leads');
    }
};
