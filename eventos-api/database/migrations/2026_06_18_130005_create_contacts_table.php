<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Global person within an organization — one record per person, joined to many
 * events via participations. Unique by email per org (architecture §6.4, §3.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // if they have a login
            $table->string('email', 180);                     // → citext below
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('company', 180)->nullable();
            $table->string('job_title', 180)->nullable();
            $table->unsignedBigInteger('photo_file_id')->nullable()->index(); // soft ref → files
            $table->jsonb('profile_data')->nullable();         // builder-defined cross-event attributes
            $table->boolean('marketing_opt_in')->default(false);
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('ALTER TABLE contacts ALTER COLUMN email TYPE citext');
        // One contact per person per org (§8.1).
        DB::statement('CREATE UNIQUE INDEX uq_contact_email ON contacts (organization_id, lower(email)) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_contacts_org ON contacts (organization_id) WHERE deleted_at IS NULL');

        // Builder attributes — GIN for containment queries (§8.4).
        DB::statement('CREATE INDEX gin_contacts_profile ON contacts USING GIN (profile_data)');

        // Full-text people search — generated tsvector kept in sync (§8.4).
        DB::statement("ALTER TABLE contacts ADD COLUMN search tsvector GENERATED ALWAYS AS (
            to_tsvector('simple',
                coalesce(first_name,'') || ' ' || coalesce(last_name,'') || ' ' || coalesce(company,''))
        ) STORED");
        DB::statement('CREATE INDEX gin_contacts_search ON contacts USING GIN (search)');
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
