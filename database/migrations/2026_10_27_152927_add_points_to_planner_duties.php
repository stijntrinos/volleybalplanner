<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A duty keeps the points that applied when it came about. Without its own
 * column every duty counted with today's points, so a change by the
 * volunteer committee also changed the duties already done.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_matches', function (Blueprint $table): void {
            $table->unsignedSmallInteger('referee_points')->nullable();
            $table->unsignedSmallInteger('scorer_points')->nullable();
        });
        Schema::table('planner_hall_duties', function (Blueprint $table): void {
            $table->unsignedSmallInteger('points')->nullable();
        });
        Schema::table('planner_volleystars_tasks', function (Blueprint $table): void {
            $table->unsignedSmallInteger('points')->nullable();
        });

        foreach (DB::table('planner_settings')->get() as $settings) {
            DB::table('planner_matches')->where('club_id', $settings->club_id)->where('match_type', 'home')->update([
                'referee_points' => (int) $settings->points_referee_duty,
                'scorer_points' => (int) $settings->points_scorer_duty,
            ]);
            DB::table('planner_hall_duties')->where('club_id', $settings->club_id)->update(['points' => (int) $settings->points_hall_duty]);
            DB::table('planner_volleystars_tasks')->where('club_id', $settings->club_id)->update(['points' => (int) $settings->points_volleystars]);
        }
    }

    public function down(): void
    {
        Schema::table('planner_matches', function (Blueprint $table): void {
            $table->dropColumn(['referee_points', 'scorer_points']);
        });
        Schema::table('planner_hall_duties', function (Blueprint $table): void {
            $table->dropColumn('points');
        });
        Schema::table('planner_volleystars_tasks', function (Blueprint $table): void {
            $table->dropColumn('points');
        });
    }
};
