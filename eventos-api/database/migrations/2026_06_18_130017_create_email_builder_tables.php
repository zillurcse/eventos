<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email builder (architecture §6.13). Reusable blocks assembled into templates;
 * a send is one rendered message feeding the delivery pipeline.
 * organization_id is nullable here — NULL rows are platform/shared assets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete(); // NULL = platform block
            $table->string('name', 180);
            $table->string('type', 30)->default('text');        // header|text|image|button|divider|footer|columns
            $table->jsonb('content')->nullable();               // block props + rich content
            $table->boolean('is_shared')->default(false);
            $table->timestampsTz();
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 80)->nullable();              // registration.confirmed|meeting.requested|custom
            $table->string('name', 180);
            $table->string('subject', 255)->nullable();         // supports merge vars
            $table->string('from_name', 180)->nullable();
            $table->string('from_email', 180)->nullable();
            $table->string('reply_to', 180)->nullable();
            $table->jsonb('design')->nullable();                // builder canvas
            $table->longText('compiled_html')->nullable();      // rendered MJML/HTML cache
            $table->string('locale', 10)->nullable();
            $table->string('status', 20)->default('draft');     // draft | published
            $table->integer('version')->default(1);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('locale')->references('code')->on('locales')->nullOnDelete();
        });

        Schema::create('email_template_blocks', function (Blueprint $table) {
            $table->foreignId('template_id')->constrained('email_templates')->cascadeOnDelete();
            $table->foreignId('block_id')->constrained('email_blocks')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->jsonb('overrides')->nullable();             // per-placement overrides
            $table->primary(['template_id', 'block_id', 'sort_order']);
        });

        Schema::create('email_sends', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->foreignId('recipient_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('to_email', 180);
            $table->string('subject', 255)->nullable();
            $table->longText('rendered_html')->nullable();
            $table->jsonb('merge_data')->nullable();
            $table->string('trigger', 80)->nullable();          // what fired the send
            $table->string('status', 20)->default('queued');    // queued|sent|delivered|opened|bounced|failed
            $table->string('provider_message_id', 180)->nullable();
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index(['template_id', 'status'], 'idx_email_sends_template');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_sends');
        Schema::dropIfExists('email_template_blocks');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('email_blocks');
    }
};
