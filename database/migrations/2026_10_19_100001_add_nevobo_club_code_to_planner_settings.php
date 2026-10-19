<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Nevobo organisation code (e.g. CNH8Q1U) the club was linked to, so its
 * teams and feeds can be fetched again later without searching.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_settings', function (Blueprint $table): void {
            $table->string('nevobo_club_code', 20)->nullable()->after('team_name_patterns');
        });
    }

    public function down(): void
    {
        Schema::table('planner_settings', function (Blueprint $table): void {
            $table->dropColumn('nevobo_club_code');
        });
    }
};
