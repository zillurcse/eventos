<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Event services catalogue (mirrors the EXPOUSE "Services" module, adapted to
 * EventOS multi-tenant conventions).
 *
 * A "service" shown in the admin UI is a GROUP of bookable options that share a
 * category, currency, tax/discount and description. Each option is its own
 * `service_items` row (so it can be ordered individually later); rows created
 * together share a `group_uuid`. RLS is enabled on both tables via the same
 * tenant_isolation policy used by the rest of the schema (§4.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['event_id']);
        });

        Schema::create('service_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                 // addresses a single option
            $table->uuid('group_uuid')->index();            // groups options of one "service"
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('service_categories')->cascadeOnDelete();

            $table->string('title', 255);                   // option name
            $table->string('unit', 60)->nullable();         // e.g. Mbps, amps, piece
            $table->char('currency', 3)->default('USD');
            $table->decimal('rate', 12, 2)->default(0);
            $table->integer('quantity_available')->nullable(); // null = unlimited
            $table->text('description')->nullable();
            $table->longText('long_description')->nullable();
            $table->string('image', 500)->nullable();

            // tax & discount
            $table->decimal('tax', 5, 2)->default(0);          // percentage
            $table->boolean('enable_discount')->default(false);
            $table->decimal('discount', 12, 2)->default(0);
            $table->enum('discount_type', ['fixed', 'percentage'])->default('fixed');
            $table->date('discount_start_date')->nullable();
            $table->date('discount_end_date')->nullable();

            // dynamic pricing (rate overrides keyed on booking date ranges)
            $table->boolean('dynamic_pricing')->default(false);
            $table->json('rate_conditions')->nullable();

            $table->boolean('is_active')->default(true);
            $table->string('status', 20)->default('pending');  // pending|approved|rejected

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['event_id', 'category_id']);
        });

        // RLS backstop — same tenant_isolation policy as the rest of the schema.
        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        foreach (['service_categories', 'service_items'] as $t) {
            DB::statement("ALTER TABLE {$t} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$t} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$t}");
            DB::statement(
                "CREATE POLICY tenant_isolation ON {$t} ".
                "USING {$predicate} WITH CHECK {$predicate}"
            );
        }
    }

    public function down(): void
    {
        foreach (['service_items', 'service_categories'] as $t) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$t}");
        }
        Schema::dropIfExists('service_items');
        Schema::dropIfExists('service_categories');
    }
};
