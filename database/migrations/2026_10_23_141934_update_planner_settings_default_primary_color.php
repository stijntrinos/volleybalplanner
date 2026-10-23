<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The brand colour moved from teal to slate. Clubs that never picked a colour
 * of their own still carry the old default and move along with it, as do
 * demo clubs still in their generated bordeaux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_settings', function (Blueprint $table): void {
            $table->string('primary_color')->nullable()->default('#2F4858')->change();
        });

        DB::table('planner_settings')
            ->whereRaw('UPPER(primary_color) = ?', ['#0B7F8A'])
            ->update(['primary_color' => '#2F4858']);

        // Demo clubs were made in bordeaux; they now show the product in its own colours.
        DB::table('planner_settings')
            ->whereIn('club_id', DB::table('clubs')->where('is_demo', true)->select('id'))
            ->whereRaw('UPPER(primary_color) = ?', ['#7A2E3A'])
            ->update(['primary_color' => '#2F4858']);
    }

    public function down(): void
    {
        Schema::table('planner_settings', function (Blueprint $table): void {
            $table->string('primary_color')->nullable()->default('#0B7F8A')->change();
        });

        DB::table('planner_settings')
            ->where('primary_color', '#2F4858')
            ->update(['primary_color' => '#0B7F8A']);
    }
};
