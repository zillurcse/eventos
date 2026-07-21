<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recommended Leads — the booth's discovery queue.
 *
 * A *lead* (exhibitor_leads) is a prospect the booth already owns: scanned,
 * typed in, or accepted from a suggestion. A *suggestion* is one step earlier:
 * an attendee the platform believes is interested, based on what they actually
 * did — messaged the booth, asked for a meeting, saved it, walked up to it, or
 * matches what the booth sells.
 *
 * The interest score itself is recomputed on every read (signals move during a
 * live event, and a stale score is worse than none). What we persist here is
 * only the part the *team* decides and must not lose: who they routed it to,
 * whether they sent a connection request, and what they dismissed so it stops
 * coming back. The score is snapshotted alongside for audit only.
 *
 * Multi-tenant conventions mirror exhibitor_leads: BelongsToOrganization +
 * Postgres RLS on the app.current_organization GUC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibitor_lead_suggestions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exhibitor_id')->constrained('exhibitors')->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained('participations')->cascadeOnDelete();

            // Routed to this teammate — the person expected to work the prospect.
            $table->foreignId('assigned_member_id')->nullable()->constrained('exhibitor_members')->nullOnDelete();
            $table->foreignId('requested_by_member_id')->nullable()->constrained('exhibitor_members')->nullOnDelete();

            // new → assigned → requested, or dismissed at any point.
            $table->string('status', 20)->default('new');
            $table->timestampTz('requested_at')->nullable();  // connection request sent
            $table->timestampTz('dismissed_at')->nullable();  // "not for us"
            $table->string('dismiss_reason', 200)->nullable();

            // Snapshot of the score/signals at the last decision, for audit.
            $table->unsignedSmallInteger('score')->default(0);
            $table->jsonb('signals')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();

            $table->unique(['exhibitor_id', 'participation_id'], 'uq_exhibitor_suggestion_participation');
            $table->index(['exhibitor_id', 'status']);
        });

        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        DB::statement('ALTER TABLE exhibitor_lead_suggestions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE exhibitor_lead_suggestions FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON exhibitor_lead_suggestions');
        DB::statement(
            'CREATE POLICY tenant_isolation ON exhibitor_lead_suggestions '.
            "USING {$predicate} WITH CHECK {$predicate}"
        );
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON exhibitor_lead_suggestions');
        Schema::dropIfExists('exhibitor_lead_suggestions');
    }
};
