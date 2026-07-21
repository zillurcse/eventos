<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attendee participation in contests (§2026_07_09_000003). One table carries
 * both shapes of participation, distinguished by `kind`:
 *
 *  - `entry`   — a submission. In an "entry" contest that's the attendee's
 *                photo/video/article; in a "response" contest it's their reply
 *                to the organizer's post.
 *  - `comment` — a comment on someone else's entry (`parent_id` set), gated by
 *                the contest's attendees_can_see_other_comments switch.
 *
 * Likes live in their own table so "most likes" winner selection and the
 * viewer's own like state are both a plain index read. Tenant isolation via
 * BelongsToOrganization + Postgres RLS, as everywhere else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contest_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('contest_entries')->cascadeOnDelete();

            $table->string('kind', 20)->default('entry'); // entry | comment
            $table->text('body')->nullable();
            // [{ kind: image|video, url, name }]
            $table->jsonb('attachments')->nullable();

            // pending | approved | rejected — starts `pending` only while the
            // contest has allow_moderate_entries on.
            $table->string('status', 20)->default('approved');

            $table->boolean('is_winner')->default(false);
            $table->unsignedInteger('rank')->nullable();
            $table->unsignedInteger('awarded_points')->default(0);

            // Denormalised counters kept in step by the controllers.
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);

            $table->jsonb('meta')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['contest_id', 'kind', 'status']);
            $table->index(['contest_id', 'like_count']);
            $table->index(['parent_id']);
            $table->index(['participation_id']);
        });

        Schema::create('contest_entry_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contest_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['contest_entry_id', 'participation_id']);
        });

        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        foreach (['contest_entries', 'contest_entry_likes'] as $name) {
            DB::statement("ALTER TABLE {$name} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$name} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$name}");
            DB::statement(
                "CREATE POLICY tenant_isolation ON {$name} ".
                "USING {$predicate} WITH CHECK {$predicate}"
            );
        }
    }

    public function down(): void
    {
        foreach (['contest_entry_likes', 'contest_entries'] as $name) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$name}");
            Schema::dropIfExists($name);
        }
    }
};
