<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The values the duty planning spreads the duties with: maxima per week and
 * day, the priority for teams playing at home, and the two halves of the
 * season the fair share is computed over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('max_duties_per_week')->default(5);
            $table->unsignedSmallInteger('max_duties_per_day')->default(4);
            $table->unsignedSmallInteger('max_hall_duties_per_day')->default(2);
            $table->unsignedSmallInteger('home_player_priority')->default(3);
            $table->unsignedSmallInteger('min_days_between_duties')->nullable();
            // Display only; the rules use the duration of each match itself.
            $table->unsignedSmallInteger('display_match_duration')->default(120);
            $table->date('half_1_start')->nullable();
            $table->date('half_1_end')->nullable();
            $table->date('half_2_start')->nullable();
            $table->date('half_2_end')->nullable();
        });

        // The day maximum was a club rule in duty_rules; it becomes a column.
        foreach (DB::table('planner_settings')->whereNotNull('duty_rules')->get(['id', 'duty_rules']) as $settings) {
            $rules = json_decode((string) $settings->duty_rules, true) ?: [];
            if (!array_key_exists('max_duties_per_day', $rules)) {
                continue;
            }

            $maximum = max(0, (int) $rules['max_duties_per_day']);
            unset($rules['max_duties_per_day']);
            DB::table('planner_settings')->where('id', $settings->id)->update([
                'max_duties_per_day' => $maximum,
                'duty_rules' => json_encode($rules),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('planner_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'max_duties_per_week', 'max_duties_per_day', 'max_hall_duties_per_day', 'home_player_priority',
                'min_days_between_duties', 'display_match_duration', 'half_1_start', 'half_1_end', 'half_2_start', 'half_2_end',
            ]);
        });
    }
};
