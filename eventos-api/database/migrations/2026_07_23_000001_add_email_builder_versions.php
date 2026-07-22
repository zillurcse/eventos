<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rounds out the email builder (architecture §6.13):
 *
 *  - `preheader`  — the inbox preview line, injected as hidden text ahead of the
 *                   body. Distinct from `subject` and needed for deliverability.
 *  - `category`   — the coarse grouping the builder's gallery filters on. `key`
 *                   stays the precise trigger id (registration.confirmed); this
 *                   is the bucket ("invitation", "reminder"…), backfilled below.
 *  - `email_template_versions` — an append-only snapshot per save, so a design
 *                   can be restored and edits are auditable.
 *
 * Gallery thumbnails deliberately get no column: the cards render the already
 * cached `compiled_html` in a scaled sandboxed iframe, lazily per card. Storing
 * a rasterized thumbnail would need a headless browser in the API container and
 * a re-render on every save, for a picture of HTML we already have.
 *
 * Reusable images intentionally get no table of their own: uploads already land
 * in `files` under the `email` collection (FileUploadController), which the
 * asset picker reads. A second table would duplicate that plumbing and its RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->string('preheader', 255)->nullable()->after('subject');
            $table->string('category', 40)->nullable()->after('key');
            $table->foreignId('created_by')->nullable()->after('version')->constrained('users')->nullOnDelete();
        });

        // Existing rows carry a dotted trigger key (registration.confirmed) or
        // nothing at all. Map the prefix onto the six buckets the API validates
        // against — storing the raw prefix would make every seeded template
        // fail validation the first time someone edited it.
        DB::statement(<<<'SQL'
            UPDATE email_templates
               SET category = CASE
                     WHEN key IS NULL OR key = '' OR key = 'custom' THEN 'custom'
                     WHEN key LIKE 'invitation.%' OR key LIKE 'invite.%' THEN 'invitation'
                     WHEN key LIKE 'reminder.%'   OR key LIKE 'event.reminder%' THEN 'reminder'
                     WHEN key LIKE 'registration.%' OR key LIKE 'order.%'
                       OR key LIKE 'ticket.%'       OR key LIKE 'payment.%' THEN 'confirmation'
                     WHEN key LIKE 'marketing.%'  OR key LIKE 'newsletter.%'
                       OR key LIKE 'campaign.%'   OR key LIKE 'post_event.%' THEN 'marketing'
                     ELSE 'system'
                   END
             WHERE category IS NULL
        SQL);

        Schema::create('email_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('email_templates')->cascadeOnDelete();
            // Not nullable: the hardened WITH CHECK below requires every write
            // to carry the acting organization, so a snapshot of a shared
            // (org-NULL) platform template belongs to the tenant that edited it.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->integer('version');
            // Everything needed to restore the document, not just the canvas —
            // subject/preheader are part of what the author was editing.
            $table->string('name', 180)->nullable();
            $table->string('subject', 255)->nullable();
            $table->string('preheader', 255)->nullable();
            $table->jsonb('design')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->nullable();

            $table->unique(['template_id', 'version']);
            $table->index(['template_id', 'id'], 'idx_email_versions_recent');
        });

        // RLS backstop, in the hardened shape from the M-3 audit
        // (2026_07_18_000002): IS NULL stays in USING so platform/shared rows
        // remain readable, but is absent from WITH CHECK — otherwise the app
        // connection could write an org-NULL row that every tenant then sees.
        $guc = "NULLIF(current_setting('app.current_organization', true), '')::bigint";

        DB::statement('ALTER TABLE email_template_versions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE email_template_versions FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON email_template_versions');
        DB::statement(
            'CREATE POLICY tenant_isolation ON email_template_versions '.
            "USING (organization_id IS NULL OR organization_id = {$guc}) ".
            "WITH CHECK (organization_id = {$guc})"
        );
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON email_template_versions');
        Schema::dropIfExists('email_template_versions');

        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['preheader', 'category']);
        });
    }
};
