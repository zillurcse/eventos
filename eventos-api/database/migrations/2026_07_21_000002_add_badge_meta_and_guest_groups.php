<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Room for role-based and guest badges.
 *
 * `badge_designs.meta` carries what does not deserve a column of its own — for
 * now the guest sub-type ("Media", "VVIP"), which turns the single `guest`
 * audience into as many guest designs as an event needs.
 *
 * `participation_groups.meta` does the same for a guest-badge batch. A batch is
 * modelled as a participation group rather than a table of its own: a group
 * already *is* "a named set of people at this event", it already carries
 * organization_id + RLS, and the rest of the product (ads, notifications) can
 * already target one. `type='guest_badge'` marks the groups the badge wizard
 * owns; `rules` stays free for dynamic segments, so config goes in `meta`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badge_designs', function (Blueprint $table) {
            $table->jsonb('meta')->nullable()->after('layers');
        });

        Schema::table('participation_groups', function (Blueprint $table) {
            $table->jsonb('meta')->nullable()->after('rules');
        });

        // Existing designs were created by the first templates page, which wrote
        // title-case labels into badge_for ("Attendee", "VIP", "Press"). The
        // audience vocabulary is lower-case slugs, and the labels that are not
        // audiences were always guest sub-types — migrate them accordingly so
        // no organizer has to re-pick a type on a design they already made.
        DB::table('badge_designs')
            ->whereNotNull('badge_for')
            ->update(['badge_for' => DB::raw('LOWER(badge_for)')]);

        foreach (['vip' => 'VIP', 'press' => 'Press', 'media' => 'Media'] as $slug => $label) {
            DB::table('badge_designs')
                ->where('badge_for', $slug)
                ->update([
                    'badge_for' => 'guest',
                    'meta' => DB::raw("jsonb_build_object('guest_type', ".DB::getPdo()->quote($label).')'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('badge_designs', fn (Blueprint $table) => $table->dropColumn('meta'));
        Schema::table('participation_groups', fn (Blueprint $table) => $table->dropColumn('meta'));
    }
};
