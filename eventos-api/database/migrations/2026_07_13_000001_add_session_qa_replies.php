<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replies to Q&A questions.
 *
 * Until now a question could only be *flagged* answered — there was nowhere to
 * put the answer itself, so the actual reply happened out loud on stage and was
 * lost to anyone reading the thread later.
 *
 * An answer is another session_messages row (kind='answer') pointing at its
 * question through parent_id, so it inherits the whole moderation apparatus
 * already in place: hide, pin, soft delete, pre-moderation status.
 *
 *   - parent_id   — the question this answers. Null for questions and chat.
 *   - author_role — 'organizer' | 'speaker' | 'attendee', snapshotted at write
 *                   time. A reply has to still read "Speaker" a year later, even
 *                   if that person is dropped from the session line-up, so the
 *                   badge cannot be recomputed from today's pivot.
 *
 * Who may reply is a per-session setting (sessions.meta.qa_answer_policy, see
 * Session::canAnswerQa) rather than a column here — it governs writes, not rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_messages', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('participation_id')
                ->constrained('session_messages')->cascadeOnDelete();
            $table->string('author_role', 12)->nullable()->after('kind');
        });

        // The Q&A read path fetches every visible answer for the questions on
        // screen in one shot — keep that a single index hit on a busy session.
        DB::statement(
            'CREATE INDEX session_messages_replies_idx ON session_messages
             (parent_id, status, is_hidden, id) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS session_messages_replies_idx');

        Schema::table('session_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('author_role');
        });
    }
};
