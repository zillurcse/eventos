<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Event feed / social, announcements, documents (architecture §6.6).
 * Authors are polymorphic: participation (attendee) OR user (organizer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->morphs('author');                          // participation | user
            $table->text('body')->nullable();
            $table->string('visibility', 20)->default('attendees'); // public|attendees|group
            $table->boolean('is_pinned')->default(false);
            $table->string('status', 20)->default('published'); // published|hidden|flagged|removed
            $table->integer('comment_count')->default(0);       // denormalized
            $table->integer('reaction_count')->default(0);      // denormalized
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('feed_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('feed_posts')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('feed_comments')->nullOnDelete(); // threading
            $table->morphs('author');
            $table->text('body')->nullable();
            $table->string('status', 20)->default('published'); // published|hidden|removed
            $table->timestampsTz();
            $table->softDeletesTz();
        });
        DB::statement('CREATE INDEX idx_feed_comments_post ON feed_comments (post_id) WHERE deleted_at IS NULL');

        Schema::create('feed_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->morphs('reactable');                        // post | comment
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('like');        // like|love|clap|insightful
            $table->timestampsTz();

            $table->unique(['reactable_type', 'reactable_id', 'participation_id'], 'uq_reaction');
            $table->index(['reactable_type', 'reactable_id'], 'idx_reactions_target');
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->jsonb('audience')->nullable();              // all | track | group filters
            $table->jsonb('channels')->nullable();              // push | email | in_app
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->string('status', 20)->default('draft');     // draft|scheduled|sent
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('document_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('document_folders')->nullOnDelete();
            $table->string('name', 180);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('document_folders')->nullOnDelete();
            $table->string('title', 200);
            $table->string('type', 20)->default('file');        // file | link
            $table->unsignedBigInteger('file_id')->nullable()->index(); // soft ref → files
            $table->string('url', 500)->nullable();
            $table->string('visibility', 20)->default('all');   // all | group
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_folders');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('feed_reactions');
        Schema::dropIfExists('feed_comments');
        Schema::dropIfExists('feed_posts');
    }
};
