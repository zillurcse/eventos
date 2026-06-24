<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File / media storage (architecture §6.8). Rows hold metadata only; bytes live
 * in S3-compatible object storage. Polymorphic owner so any entity can attach.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete(); // NULL = platform asset
            $table->nullableMorphs('attachable');               // polymorphic owner
            $table->string('collection', 60)->nullable();       // avatar|cover|logo|document|badge|export
            $table->string('disk', 40)->default('s3');          // s3 | local
            $table->string('path', 500);                        // object key
            $table->string('filename', 255)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->string('checksum', 64)->nullable();         // SHA-256 dedupe
            $table->string('visibility', 20)->default('private'); // public|private|signed
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('meta')->nullable();                  // width/height/duration
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('media_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);                         // thumb|medium|webp
            $table->string('path', 500);
            $table->string('mime_type', 120)->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_conversions');
        Schema::dropIfExists('files');
    }
};
