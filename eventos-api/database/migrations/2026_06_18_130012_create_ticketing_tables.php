<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ticketing & registration (architecture §6.4). Registration itself is a
 * published form-builder form; submitting it creates a contact + participation
 * and (for paid events) an order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('description', 500)->nullable();
            $table->bigInteger('price_cents')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->integer('quantity')->nullable();           // capacity
            $table->integer('sold')->default(0);
            $table->timestampTz('sales_start')->nullable();
            $table->timestampTz('sales_end')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('min_per_order')->default(1);
            $table->integer('max_per_order')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('type', 20)->default('percent');    // percent | fixed
            $table->bigInteger('value')->default(0);
            $table->integer('max_uses')->nullable();
            $table->integer('used')->default(0);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->unique(['event_id', 'code']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('number', 40)->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('buyer_email', 180)->nullable();
            $table->string('status', 20)->default('pending');  // pending|paid|refunded|canceled
            $table->bigInteger('subtotal_cents')->default(0);
            $table->bigInteger('discount_cents')->default(0);
            $table->bigInteger('tax_cents')->default(0);
            $table->bigInteger('total_cents')->default(0);
            $table->foreignId('discount_code_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('payment_id')->nullable()->index(); // soft ref → payments
            $table->timestampsTz();

            $table->index(['event_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->bigInteger('unit_price_cents')->default(0);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                    // QR payload
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->nullable()->constrained()->nullOnDelete(); // until assigned
            $table->string('status', 20)->default('issued');   // issued|used|void|transferred
            $table->string('qr_token', 120)->unique();         // signed
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('discount_codes');
        Schema::dropIfExists('ticket_types');
    }
};
