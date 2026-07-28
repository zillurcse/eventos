<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comments store @mention targets in meta.mentions (same shape as feed posts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_comments', function (Blueprint $table) {
            $table->jsonb('meta')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('feed_comments', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
