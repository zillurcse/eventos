<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event sponsor CTAs (Communication → CTA). Three flavours:
 *   - image: title + button label/link + a banner image
 *   - video: an ordered list of { platform, url, caption } links
 *   - text:  title + rich-text description + button label/link
 * RLS uses the same tenant_isolation policy as the rest of the schema (§4.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ctas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                                        // image | video | text
            $table->string('title', 255)->nullable();
            $table->longText('description')->nullable();                       // text type (rich HTML)
            $table->string('button_label', 120)->nullable();
            $table->string('button_link', 500)->nullable();
            $table->unsignedBigInteger('image_file_id')->nullable()->index();  // soft ref → files (image type)
            $table->jsonb('videos')->nullable();                              // [{ platform, url, caption }] (video type)
            $table->integer('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['event_id', 'position']);
        });

        // RLS backstop — same tenant_isolation policy as the rest of the schema.
        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        DB::statement('ALTER TABLE ctas ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ctas FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON ctas');
        DB::statement(
            "CREATE POLICY tenant_isolation ON ctas ".
            "USING {$predicate} WITH CHECK {$predicate}"
        );
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON ctas');
        Schema::dropIfExists('ctas');
    }
};
