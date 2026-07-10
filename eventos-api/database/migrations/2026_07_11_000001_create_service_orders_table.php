<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Order header for exhibitor service requests. Each time a booth submits its
 * basket from "Request Service" it creates one service_orders row; the basket
 * lines stay in service_requests, now pointing at their order.
 *
 * The order has no status column of its own — it is derived from its lines, so
 * an order whose lines the organizer approved one-by-one reads as "partial"
 * until every line agrees. See App\Models\ServiceOrder::status().
 *
 * Because a booth may now order the same catalogue item in two separate orders,
 * the old unique(exhibitor_id, service_item_id) constraint is dropped.
 *
 * Multi-tenant conventions mirror service_requests: BelongsToOrganization +
 * Postgres RLS on the app.current_organization GUC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exhibitor_id')->constrained('exhibitors')->cascadeOnDelete();

            // Human-facing reference shown to both sides, e.g. SER-20250000003.
            $table->string('order_number', 32)->unique();
            $table->timestampTz('submitted_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['event_id', 'exhibitor_id']);
        });

        DB::statement('CREATE SEQUENCE IF NOT EXISTS service_order_number_seq START 1');

        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        DB::statement('ALTER TABLE service_orders ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE service_orders FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON service_orders');
        DB::statement(
            'CREATE POLICY tenant_isolation ON service_orders '.
            "USING {$predicate} WITH CHECK {$predicate}"
        );

        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreignId('service_order_id')->nullable()->after('exhibitor_id')
                ->constrained('service_orders')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE service_requests DROP CONSTRAINT IF EXISTS uq_service_request');

        $this->backfillOrders();

        DB::statement('ALTER TABLE service_requests ALTER COLUMN service_order_id SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropForeign(['service_order_id']);
            $table->dropColumn('service_order_id');
        });

        // Restore the pre-order constraint; drop duplicate lines first so it can build.
        DB::statement(<<<'SQL'
            DELETE FROM service_requests a USING service_requests b
            WHERE a.id > b.id
              AND a.exhibitor_id = b.exhibitor_id
              AND a.service_item_id = b.service_item_id
        SQL);
        DB::statement('ALTER TABLE service_requests ADD CONSTRAINT uq_service_request UNIQUE (exhibitor_id, service_item_id)');

        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON service_orders');
        Schema::dropIfExists('service_orders');
        DB::statement('DROP SEQUENCE IF EXISTS service_order_number_seq');
    }

    /** Existing loose request lines become one order per booth, dated from their oldest line. */
    private function backfillOrders(): void
    {
        $groups = DB::table('service_requests')
            ->selectRaw('organization_id, event_id, exhibitor_id, MIN(created_at) AS first_at')
            ->whereNull('service_order_id')
            ->groupBy('organization_id', 'event_id', 'exhibitor_id')
            ->get();

        foreach ($groups as $group) {
            $seq = DB::selectOne("SELECT nextval('service_order_number_seq') AS n")->n;

            $orderId = DB::table('service_orders')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $group->organization_id,
                'event_id' => $group->event_id,
                'exhibitor_id' => $group->exhibitor_id,
                'order_number' => 'SER-'.date('Y', strtotime($group->first_at)).str_pad((string) $seq, 7, '0', STR_PAD_LEFT),
                'submitted_at' => $group->first_at,
                'created_at' => $group->first_at,
                'updated_at' => $group->first_at,
            ]);

            DB::table('service_requests')
                ->whereNull('service_order_id')
                ->where('organization_id', $group->organization_id)
                ->where('event_id', $group->event_id)
                ->where('exhibitor_id', $group->exhibitor_id)
                ->update(['service_order_id' => $orderId]);
        }
    }
};
