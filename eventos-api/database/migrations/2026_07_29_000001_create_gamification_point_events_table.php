<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only ledger of gamification point awards. Each row is one scored
 * action for one participation. `idempotency_key` prevents double-credit when
 * the queued award job is retried or the attendee repeats a once-only action.
 *
 * `participations.points_total` is the denormalised running balance for
 * leaderboard reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_point_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete();
            $table->string('action_key', 80);
            $table->unsignedInteger('points');
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('idempotency_key', 190);
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();

            $table->unique(['event_id', 'idempotency_key']);
            $table->index(['event_id', 'participation_id']);
            $table->index(['event_id', 'action_key']);
            $table->index(['participation_id', 'created_at']);
        });

        Schema::table('participations', function (Blueprint $table) {
            $table->unsignedInteger('points_total')->default(0)->after('networking_opt_in');
        });

        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        DB::statement('ALTER TABLE gamification_point_events ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE gamification_point_events FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON gamification_point_events');
        DB::statement(
            "CREATE POLICY tenant_isolation ON gamification_point_events ".
            "USING {$predicate} WITH CHECK {$predicate}"
        );
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON gamification_point_events');
        Schema::dropIfExists('gamification_point_events');

        Schema::table('participations', function (Blueprint $table) {
            $table->dropColumn('points_total');
        });
    }
};
