<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attendee ↔ exhibitor "Contact" layer. Unlike participant chat/meetings (which
 * are participation-paired), the recipient here is an exhibitor *company*; the
 * exhibitor admin later assigns one of its members to handle a thread or a
 * meeting request. Three tables:
 *
 *  - exhibitor_conversations: one thread per (event, exhibitor, attendee),
 *    optionally routed to an assigned member.
 *  - exhibitor_messages: the thread's messages, sent by the attendee or by the
 *    exhibitor side (a member).
 *  - exhibitor_meeting_requests: an attendee's meeting request against an
 *    exhibitor; requested → assigned (a member picked) → confirmed / declined.
 *
 * Multi-tenant conventions mirror chat_* (§4.3): BelongsToOrganization +
 * Postgres RLS on the app.current_organization GUC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibitor_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exhibitor_id')->constrained('exhibitors')->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained('participations')->cascadeOnDelete();
            // The exhibitor teammate handling this thread (assigned by the admin).
            $table->foreignId('assigned_member_id')->nullable()->constrained('exhibitor_members')->nullOnDelete();

            $table->timestampTz('last_message_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();

            $table->unique(['event_id', 'exhibitor_id', 'participation_id'], 'uq_exh_convo');
            $table->index(['exhibitor_id', 'last_message_at']);
            $table->index(['participation_id', 'last_message_at']);
        });

        Schema::create('exhibitor_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('exhibitor_conversations')->cascadeOnDelete();

            // Exactly one sender side is set: attendee (participation) or the
            // exhibitor (a member). `sender_side` disambiguates for the UI.
            $table->string('sender_side', 16); // attendee | exhibitor
            $table->foreignId('sender_participation_id')->nullable()->constrained('participations')->nullOnDelete();
            $table->foreignId('sender_member_id')->nullable()->constrained('exhibitor_members')->nullOnDelete();

            $table->text('body');
            $table->timestampTz('read_at')->nullable(); // read by the other side
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();

            $table->index(['conversation_id', 'id']);
            $table->index(['conversation_id', 'read_at']);
        });

        Schema::create('exhibitor_meeting_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exhibitor_id')->constrained('exhibitors')->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained('participations')->cascadeOnDelete();
            // Set once the exhibitor admin assigns a teammate to the meeting.
            $table->foreignId('assigned_member_id')->nullable()->constrained('exhibitor_members')->nullOnDelete();

            // requested → assigned → confirmed | declined | canceled
            $table->string('status', 20)->default('requested');
            $table->string('subject', 200)->nullable();
            $table->text('agenda')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->jsonb('meta')->nullable(); // lounge_date / lounge_slot
            $table->timestampsTz();

            $table->index(['exhibitor_id', 'status']);
            $table->index(['participation_id', 'status']);
        });

        $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";

        foreach (['exhibitor_conversations', 'exhibitor_messages', 'exhibitor_meeting_requests'] as $t) {
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
        foreach (['exhibitor_meeting_requests', 'exhibitor_messages', 'exhibitor_conversations'] as $t) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$t}");
        }
        Schema::dropIfExists('exhibitor_meeting_requests');
        Schema::dropIfExists('exhibitor_messages');
        Schema::dropIfExists('exhibitor_conversations');
    }
};
