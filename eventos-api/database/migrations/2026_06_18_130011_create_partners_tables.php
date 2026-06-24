<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unified partner model — exhibitor | sponsor via a type discriminator
 * (architecture §6.3). All sub-features (members, documents, products,
 * projects, booths) are shared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);                       // Platinum, Gold, Standard Booth
            $table->string('kind', 20)->default('both');       // exhibitor|sponsor|both
            $table->bigInteger('price_cents')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->jsonb('entitlements')->nullable();         // {booths, member_seats, products_max, ...}
            $table->integer('rank')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('exhibitor');  // exhibitor | sponsor
            $table->foreignId('package_id')->nullable()->constrained('partner_packages')->nullOnDelete();
            $table->string('name', 180);
            $table->string('slug', 180);
            $table->string('description', 1000)->nullable();
            $table->string('website', 255)->nullable();
            $table->unsignedBigInteger('logo_file_id')->nullable()->index(); // soft ref → files
            $table->integer('tier_rank')->default(0);
            $table->foreignId('admin_contact_id')->nullable()->constrained('contacts')->nullOnDelete(); // partner admin login
            $table->jsonb('placements')->nullable();
            $table->jsonb('profile_data')->nullable();         // builder-defined projection
            $table->string('status', 20)->default('draft');    // draft|active|suspended
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
        DB::statement('CREATE UNIQUE INDEX uq_partner_slug ON partners (event_id, slug) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_partners_event_type ON partners (event_id, type) WHERE deleted_at IS NULL');

        Schema::create('partner_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 30)->default('staff');      // admin | staff (admin can log in)
            $table->boolean('is_lead_capturer')->default(false);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['partner_id', 'contact_id']);
            $table->index('partner_id');
        });

        Schema::create('partner_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->unsignedBigInteger('file_id')->nullable()->index(); // soft ref → files
            $table->string('url', 500)->nullable();
            $table->string('visibility', 20)->default('all');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('partner_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('description', 1000)->nullable();
            $table->bigInteger('price_cents')->nullable();
            $table->unsignedBigInteger('image_file_id')->nullable()->index(); // soft ref → files
            $table->jsonb('meta')->nullable();                 // specs, links, category
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('partner_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('description', 1000)->nullable();
            $table->string('status', 30)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('booths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete(); // type=exhibitor
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 60)->nullable();
            $table->string('type', 20)->default('physical');   // physical | virtual
            $table->jsonb('resources')->nullable();            // videos, docs, chat link
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booths');
        Schema::dropIfExists('partner_projects');
        Schema::dropIfExists('partner_products');
        Schema::dropIfExists('partner_documents');
        Schema::dropIfExists('partner_members');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('partner_packages');
    }
};
