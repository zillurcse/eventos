<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription & billing schema (architecture §6.2).
 * Platform-level: plans, features (catalog), coupons.
 * Tenant-level (RLS): subscriptions, invoices, payments, usage_meters.
 * Money is stored as BIGINT minor units, never float.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120);                      // Free, Pro, Business, Enterprise
            $table->string('slug', 120)->unique();
            $table->string('billing_interval', 20)->default('month'); // month|year|custom
            $table->bigInteger('price_cents')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->integer('trial_days')->default(0);
            $table->jsonb('limits')->nullable();              // {max_events, max_attendees, storage_gb, api_rate}
            $table->boolean('is_public')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('gateway_price_id', 120)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->jsonb('value')->nullable();               // boolean enabled OR quota number
            $table->unique(['plan_id', 'feature_id']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('status', 20)->default('trialing'); // trialing|active|past_due|canceled|paused
            $table->string('gateway', 30)->nullable();         // stripe|sslcommerz|manual
            $table->string('gateway_subscription_id', 120)->nullable();
            $table->integer('quantity')->default(1);           // seats
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('current_period_start')->nullable();
            $table->timestampTz('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestampTz('canceled_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained();    // add-on
            $table->integer('quantity')->default(1);
            $table->bigInteger('unit_price_cents')->default(0);
            $table->string('gateway_item_id', 120)->nullable();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40)->unique();            // human invoice number
            $table->string('status', 20)->default('draft');    // draft|open|paid|void|uncollectible
            $table->char('currency', 3)->default('USD');
            $table->bigInteger('subtotal_cents')->default(0);
            $table->bigInteger('tax_cents')->default(0);
            $table->bigInteger('discount_cents')->default(0);
            $table->bigInteger('total_cents')->default(0);
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->string('gateway_invoice_id', 120)->nullable();
            $table->unsignedBigInteger('pdf_file_id')->nullable()->index(); // soft ref → files
            $table->timestampsTz();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->integer('quantity')->default(1);
            $table->bigInteger('unit_price_cents')->default(0);
            $table->bigInteger('amount_cents')->default(0);
            $table->timestampTz('period_start')->nullable();
            $table->timestampTz('period_end')->nullable();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 30)->nullable();          // stripe|bkash|nagad|sslcommerz
            $table->string('gateway_payment_id', 120)->nullable();
            $table->bigInteger('amount_cents')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('status', 20)->default('pending');   // pending|succeeded|failed
            $table->string('method', 30)->nullable();           // card|mobile_banking|bank
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount_cents')->default(0);
            $table->string('reason', 255)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestampsTz();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('type', 20)->default('percent');     // percent | fixed
            $table->bigInteger('value')->default(0);
            $table->integer('max_redemptions')->nullable();
            $table->integer('redeemed')->default(0);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('usage_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained();
            $table->timestampTz('period_start')->nullable();
            $table->timestampTz('period_end')->nullable();
            $table->bigInteger('used')->default(0);
            $table->bigInteger('limit')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_meters');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
    }
};
