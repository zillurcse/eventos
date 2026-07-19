<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plan-change requests: an organizer asks to move to another plan and a platform
 * super-admin approves (activates the plan) or rejects it. Tenant-isolated like
 * the rest (BelongsToOrganization + Postgres RLS keyed on app.current_organization),
 * but the super-admin reads/writes cross-tenant on the BYPASSRLS connection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_change_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('current_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('requested_plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('status', 12)->default('pending'); // pending | approved | rejected | canceled
            $table->text('note')->nullable();          // organizer's message
            $table->text('review_note')->nullable();   // admin's reason (esp. on reject)
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'status', 'id']);
        });

        // Only one open (pending) request per organization.
        DB::statement(
            'CREATE UNIQUE INDEX plan_change_requests_one_pending
             ON plan_change_requests (organization_id) WHERE status = \'pending\''
        );

        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";
        DB::statement('ALTER TABLE plan_change_requests ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE plan_change_requests FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON plan_change_requests');
        DB::statement("CREATE POLICY tenant_isolation ON plan_change_requests USING {$predicate} WITH CHECK {$predicate}");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON plan_change_requests');
        Schema::dropIfExists('plan_change_requests');
    }
};
