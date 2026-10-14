<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per team the licence a referee needs at minimum for its home matches, so
 * teams on referee duty can see beforehand whether they may take the match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_teams', function (Blueprint $table): void {
            $table->string('referee_license', 10)->nullable()->after('provides_referee');
        });
    }

    public function down(): void
    {
        Schema::table('planner_teams', function (Blueprint $table): void {
            $table->dropColumn('referee_license');
        });
    }
};
