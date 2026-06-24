<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic translated strings (architecture §6.10). Any localizable entity
 * field resolves through here with locale fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->morphs('translatable');
            $table->string('field', 60);                        // title, description...
            $table->string('locale', 10);
            $table->text('value')->nullable();
            $table->timestampsTz();

            $table->unique(['translatable_type', 'translatable_id', 'field', 'locale'], 'uq_translation');
            $table->foreign('locale')->references('code')->on('locales')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
