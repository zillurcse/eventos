<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tokenised upload links so a hired photographer can drop shots into an event
 * without an account. The token is a bearer capability — narrow by design:
 * revocable, optionally expiring, optionally capped, and scoped to one event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expolens_upload_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('label', 150);
            $table->string('album', 120)->nullable();
            // Trusted photographers can skip the moderation queue.
            $table->boolean('auto_approve')->default(false);
            $table->timestampTz('expires_at')->nullable();
            $table->unsignedInteger('max_uploads')->nullable();
            $table->unsignedInteger('uploads_count')->default(0);
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['event_id', 'revoked_at']);
        });

        $predicate = "(organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";
        DB::statement('ALTER TABLE expolens_upload_links ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE expolens_upload_links FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_isolation ON expolens_upload_links USING {$predicate} WITH CHECK {$predicate}");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON expolens_upload_links');
        Schema::dropIfExists('expolens_upload_links');
    }
};
