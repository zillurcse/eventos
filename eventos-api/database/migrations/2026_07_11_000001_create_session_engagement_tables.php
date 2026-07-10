<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Live session engagement (attendee watch page side-panel): group Chat, Q&A
 * (questions + upvotes) and live Polls. Tenant-isolated like the rest
 * (BelongsToOrganization + Postgres RLS keyed on app.current_organization).
 * Chat + Q&A share one table keyed by `kind`; polls get their own table plus a
 * one-row-per-voter votes table so tallies can't be double-counted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 12)->default('chat'); // chat | question
            $table->text('body');
            $table->unsignedInteger('upvotes')->default(0);
            $table->boolean('is_answered')->default(false);
            $table->jsonb('meta')->nullable(); // voter ids, author name snapshot
            $table->timestampsTz();

            $table->index(['session_id', 'kind', 'id']);
        });

        Schema::create('session_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained()->cascadeOnDelete();
            $table->string('question', 300);
            $table->jsonb('options'); // [{ id, text }]
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['session_id', 'is_active']);
        });

        Schema::create('session_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete();
            $table->string('option_id', 20);
            $table->timestampsTz();

            $table->unique(['session_poll_id', 'participation_id']);
        });

        foreach (['session_messages', 'session_polls', 'session_poll_votes'] as $t) {
            $predicate = "(organization_id IS NULL OR organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";
            DB::statement("ALTER TABLE {$t} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$t} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$t}");
            DB::statement("CREATE POLICY tenant_isolation ON {$t} USING {$predicate} WITH CHECK {$predicate}");
        }
    }

    public function down(): void
    {
        foreach (['session_poll_votes', 'session_polls', 'session_messages'] as $t) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$t}");
            Schema::dropIfExists($t);
        }
    }
};
