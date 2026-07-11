<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Host moderation for the live-session engagement panel.
 *
 * The first cut of session_messages / session_polls had no moderation at all:
 * anything posted was visible to everyone, forever. This adds the controls a
 * host actually needs while a session is running.
 *
 * session_messages (chat + Q&A):
 *   - is_hidden  — pulled from the attendee view but still shown to the host,
 *                  so a hide is reversible (unlike a delete).
 *   - status     — 'published' | 'pending' | 'rejected'. Questions start
 *                  'pending' when the session has Q&A pre-moderation on
 *                  (sessions.meta.qa_moderation); chat is always 'published'.
 *   - is_pinned / answered_at — the standard Q&A affordances.
 *   - deleted_at — deletes are soft so a mis-click is recoverable and the row
 *                  survives for audit.
 *
 * session_polls: `is_active` was a single boolean, which can't express "written
 * but not launched yet". It becomes a draft → live → closed lifecycle, plus
 * show_results (hide the tally until the host closes voting) and a
 * created_by_participation_id for polls a host authors from the watch page
 * (created_by points at users, and hosts act as participations there).
 *
 * session_mutes: a host silences a spammer for one session instead of chasing
 * each message they post.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_messages', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('is_answered');
            $table->boolean('is_pinned')->default(false)->after('is_hidden');
            $table->string('status', 12)->default('published')->after('kind'); // published|pending|rejected
            $table->timestampTz('answered_at')->nullable()->after('is_answered');
            $table->foreignId('moderated_by')->nullable()->after('meta')
                ->constrained('participations')->nullOnDelete();
            $table->timestampTz('moderated_at')->nullable()->after('moderated_by');
            $table->softDeletesTz();
        });

        // The attendee read path is "this session's visible chat/questions" —
        // keep it a single index hit as a room fills up.
        DB::statement(
            'CREATE INDEX session_messages_visible_idx ON session_messages
             (session_id, kind, status, is_hidden, id) WHERE deleted_at IS NULL'
        );

        Schema::table('session_polls', function (Blueprint $table) {
            $table->string('status', 10)->default('live')->after('options'); // draft|live|closed
            $table->boolean('show_results')->default(true)->after('status');
            $table->timestampTz('published_at')->nullable()->after('show_results');
            $table->timestampTz('closed_at')->nullable()->after('published_at');
            $table->foreignId('created_by_participation_id')->nullable()->after('created_by')
                ->constrained('participations')->nullOnDelete();
            $table->softDeletesTz();
        });

        // Carry the old boolean over before it goes away.
        DB::table('session_polls')->where('is_active', true)
            ->update(['status' => 'live', 'published_at' => now()]);
        DB::table('session_polls')->where('is_active', false)
            ->update(['status' => 'closed', 'closed_at' => now()]);

        Schema::table('session_polls', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::create('session_mutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('muted_by')->nullable()->constrained('participations')->nullOnDelete();
            $table->string('reason', 200)->nullable();
            $table->timestampsTz();

            $table->unique(['session_id', 'participation_id']);
        });

        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";
        DB::statement('ALTER TABLE session_mutes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE session_mutes FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON session_mutes');
        DB::statement("CREATE POLICY tenant_isolation ON session_mutes USING {$predicate} WITH CHECK {$predicate}");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON session_mutes');
        Schema::dropIfExists('session_mutes');

        Schema::table('session_polls', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });
        DB::table('session_polls')->update(['is_active' => DB::raw("status = 'live'")]);
        Schema::table('session_polls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_participation_id');
            $table->dropColumn(['status', 'show_results', 'published_at', 'closed_at', 'deleted_at']);
        });

        DB::statement('DROP INDEX IF EXISTS session_messages_visible_idx');
        Schema::table('session_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn(['is_hidden', 'is_pinned', 'status', 'answered_at', 'moderated_at', 'deleted_at']);
        });
    }
};
