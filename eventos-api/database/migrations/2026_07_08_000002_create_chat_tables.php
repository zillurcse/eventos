<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-to-one chat between event participants (attendee ↔ attendee / speaker /
 * exhibitor / sponsor). A conversation is an unordered participation pair,
 * normalized as (a_participation_id < b_participation_id) so a pair can only
 * ever have one row. Unread state is per message (`read_at`); denormalized
 * `last_message_at` orders the inbox. Who may chat with whom is governed by
 * the per-event role matrix in event_settings.chat (Communication → Chat).
 *
 * Multi-tenant conventions mirror breakout_rooms (§4.3): BelongsToOrganization
 * + Postgres RLS on the app.current_organization GUC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // Normalized pair: a < b, enforced app-side, deduped by the unique.
            $table->foreignId('a_participation_id')->constrained('participations')->cascadeOnDelete();
            $table->foreignId('b_participation_id')->constrained('participations')->cascadeOnDelete();

            $table->timestampTz('last_message_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();

            $table->unique(['event_id', 'a_participation_id', 'b_participation_id'], 'uq_chat_pair');
            $table->index(['a_participation_id', 'last_message_at']);
            $table->index(['b_participation_id', 'last_message_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('sender_participation_id')->constrained('participations')->cascadeOnDelete();

            $table->text('body');
            $table->timestampTz('read_at')->nullable(); // read by the counterpart
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();

            // Thread reads + the per-conversation unread count (read_at IS NULL).
            $table->index(['conversation_id', 'id']);
            $table->index(['conversation_id', 'read_at']);
        });

        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        foreach (['chat_conversations', 'chat_messages'] as $t) {
            DB::statement("ALTER TABLE {$t} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$t} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$t}");
            DB::statement(
                "CREATE POLICY tenant_isolation ON {$t} ".
                "USING {$predicate} WITH CHECK {$predicate}"
            );
        }
    }

    public function down(): void
    {
        foreach (['chat_messages', 'chat_conversations'] as $t) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$t}");
        }
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
