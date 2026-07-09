<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exhibitor service requests. An exhibitor booth orders items from the event's
 * Services catalogue (service_items, defined by the organizer). One row per
 * (exhibitor, service_item); quantity + a price snapshot are stored so the line
 * total is stable if the organizer later edits the catalogue. Organizer moves
 * it pending → approved | rejected.
 *
 * Multi-tenant conventions mirror the exhibitor_* tables: BelongsToOrganization
 * + Postgres RLS on the app.current_organization GUC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exhibitor_id')->constrained('exhibitors')->cascadeOnDelete();
            $table->foreignId('service_item_id')->constrained('service_items')->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);
            // Snapshot of the catalogue price at request time.
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->char('currency', 3)->default('USD');

            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['exhibitor_id', 'service_item_id'], 'uq_service_request');
            $table->index(['exhibitor_id', 'status']);
            $table->index(['event_id', 'status']);
        });

        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        DB::statement('ALTER TABLE service_requests ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE service_requests FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON service_requests');
        DB::statement(
            'CREATE POLICY tenant_isolation ON service_requests '.
            "USING {$predicate} WITH CHECK {$predicate}"
        );
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON service_requests');
        Schema::dropIfExists('service_requests');
    }
};
