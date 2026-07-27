<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Package builder additions: description, tier, and booth size shown in the
 * "Add Package" drawer alongside the existing name/price/entitlements fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhibitor_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('exhibitor_packages', 'description')) {
                $table->string('description', 1000)->nullable();
            }
            if (! Schema::hasColumn('exhibitor_packages', 'tier')) {
                $table->string('tier', 30)->default('standard');
            }
            if (! Schema::hasColumn('exhibitor_packages', 'booth_size')) {
                $table->string('booth_size', 30)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('exhibitor_packages', function (Blueprint $table) {
            foreach (['description', 'tier', 'booth_size'] as $col) {
                if (Schema::hasColumn('exhibitor_packages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
