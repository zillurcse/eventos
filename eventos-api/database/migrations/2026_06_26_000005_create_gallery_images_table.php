<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event image gallery (Content Hub → Image Gallery). Each row is one photo,
 * optionally grouped into a named album. RLS is enabled with the same
 * tenant_isolation policy used across the schema (§4.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_images', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('file_id')->nullable()->index(); // soft ref → files
            $table->string('url', 1000);                                // cached public URL
            $table->string('caption', 255)->nullable();
            $table->string('album', 120)->nullable();                   // null = "General"
            $table->integer('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['event_id', 'album']);
            $table->index(['event_id', 'sort_order']);
        });

        // RLS backstop — same tenant_isolation policy as the rest of the schema.
        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        DB::statement('ALTER TABLE gallery_images ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE gallery_images FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON gallery_images');
        DB::statement(
            "CREATE POLICY tenant_isolation ON gallery_images ".
            "USING {$predicate} WITH CHECK {$predicate}"
        );
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON gallery_images');
        Schema::dropIfExists('gallery_images');
    }
};
