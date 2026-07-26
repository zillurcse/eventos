<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'expolens_photos',
        'expolens_face_embeddings',
        'expolens_photo_faces',
        'expolens_photo_matches',
    ];

    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('expolens_photos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('file_id')->index();
            $table->string('url', 1000);
            $table->string('caption', 255)->nullable();
            $table->string('album', 120)->nullable();
            $table->string('processing_status', 20)->default('pending');
            $table->string('moderation_status', 20)->default('pending');
            $table->unsignedInteger('faces_count')->default(0);
            $table->text('failure_reason')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('photographer_meta')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['event_id', 'moderation_status', 'created_at']);
            $table->index(['event_id', 'processing_status']);
        });

        Schema::create('expolens_face_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20)->default('avatar');
            $table->string('model', 60)->default('buffalo_l');
            $table->unsignedSmallInteger('dimensions')->default(512);
            $table->decimal('quality_score', 8, 6)->nullable();
            $table->string('source_url', 1000)->nullable();
            $table->timestampTz('consented_at');
            $table->timestampTz('enrolled_at');
            $table->timestampsTz();

            $table->unique(['participation_id', 'source']);
            $table->index(['event_id', 'model']);
        });
        DB::statement('ALTER TABLE expolens_face_embeddings ADD COLUMN embedding vector(512) NOT NULL');

        Schema::create('expolens_photo_faces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('photo_id')->constrained('expolens_photos')->cascadeOnDelete();
            $table->jsonb('bbox');
            $table->decimal('detection_score', 8, 6);
            $table->decimal('quality_score', 8, 6)->nullable();
            $table->string('model', 60)->default('buffalo_l');
            $table->timestampsTz();

            $table->index('photo_id');
        });
        DB::statement('ALTER TABLE expolens_photo_faces ADD COLUMN embedding vector(512) NOT NULL');

        Schema::create('expolens_photo_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('photo_id')->constrained('expolens_photos')->cascadeOnDelete();
            $table->foreignId('photo_face_id')->constrained('expolens_photo_faces')->cascadeOnDelete();
            $table->foreignId('participation_id')->constrained()->cascadeOnDelete();
            $table->decimal('similarity_score', 8, 6);
            $table->boolean('confirmed')->default(false);
            $table->timestampsTz();

            $table->unique(['photo_id', 'participation_id']);
            $table->index(['participation_id', 'similarity_score']);
        });

        $predicate = "(organization_id = NULLIF(current_setting('app.current_organization', true), '')::bigint)";
        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_isolation ON {$table} USING {$predicate} WITH CHECK {$predicate}");
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            Schema::dropIfExists($table);
        }
    }
};
