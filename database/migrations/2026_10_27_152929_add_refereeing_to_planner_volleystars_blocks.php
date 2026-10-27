<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A VolleyStars duty knows refereeing next to scoring. Refereeing runs with
 * scoring by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_volleystars_blocks', function (Blueprint $table): void {
            $table->time('refereeing_start')->default('08:45:00')->after('scoring_end');
            $table->time('refereeing_end')->default('12:30:00')->after('refereeing_start');
        });

        DB::table('planner_volleystars_blocks')->update([
            'refereeing_start' => DB::raw('scoring_start'),
            'refereeing_end' => DB::raw('scoring_end'),
        ]);
    }

    public function down(): void
    {
        Schema::table('planner_volleystars_blocks', function (Blueprint $table): void {
            $table->dropColumn(['refereeing_start', 'refereeing_end']);
        });
    }
};
