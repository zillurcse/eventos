<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event blog / news articles shown in the Content Hub and on the attendee
 * website. RLS is enabled with the same tenant_isolation policy used across the
 * schema (§4.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('slug', 255);
            $table->string('excerpt', 500)->nullable();
            $table->longText('body')->nullable();
            $table->unsignedBigInteger('cover_file_id')->nullable()->index(); // soft ref → files
            $table->string('status', 20)->default('draft');                   // draft | published
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['event_id', 'status']);
        });

        DB::statement('CREATE UNIQUE INDEX uq_blog_post_slug ON blog_posts (event_id, slug) WHERE deleted_at IS NULL');

        // RLS backstop — same tenant_isolation policy as the rest of the schema.
        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        DB::statement('ALTER TABLE blog_posts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE blog_posts FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON blog_posts');
        DB::statement(
            "CREATE POLICY tenant_isolation ON blog_posts ".
            "USING {$predicate} WITH CHECK {$predicate}"
        );
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON blog_posts');
        Schema::dropIfExists('blog_posts');
    }
};
