<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rich feed posts: the composer can publish text, image/video/PDF media, polls,
 * and "looking for" / "offering" networking posts. The post kind and its
 * type-specific payload (attachments, poll options + votes, tags) live in the
 * `meta` JSONB so no per-type columns are needed. RLS on feed_posts is already
 * enabled table-wide, so a new column needs no policy change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_posts', function (Blueprint $table) {
            $table->jsonb('meta')->nullable()->after('reaction_count');
        });
    }

    public function down(): void
    {
        Schema::table('feed_posts', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
