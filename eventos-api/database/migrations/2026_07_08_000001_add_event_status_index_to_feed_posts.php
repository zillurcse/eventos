<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every feed read filters `event_id + status` (the attendee wall reads
 * published posts, the admin moderation tabs read pending/published/rejected)
 * and orders by id — but feed_posts only had indexes on uuid and the author
 * morph, so each request walked the whole table. Composite (event_id, status,
 * id) covers the filter AND the `latest('id')` sort; partial on live rows to
 * match the SoftDeletes scope every query applies.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE INDEX IF NOT EXISTS idx_feed_posts_event_status
             ON feed_posts (event_id, status, id DESC)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_feed_posts_event_status');
    }
};
