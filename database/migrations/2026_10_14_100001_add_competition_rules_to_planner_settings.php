<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Competition rules that keep move proposals within what the federation
 * allows. Dates are recurring "MM-DD" values; empty means the rule is off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planner_settings', function (Blueprint $table): void {
            $table->string('youth_spring_start', 5)->nullable()->after('competition_end');
            $table->string('youth_spring_end', 5)->nullable()->after('youth_spring_start');
            $table->string('internal_senior_deadline', 5)->nullable()->after('youth_spring_end');
        });
    }

    public function down(): void
    {
        Schema::table('planner_settings', function (Blueprint $table): void {
            $table->dropColumn(['youth_spring_start', 'youth_spring_end', 'internal_senior_deadline']);
        });
    }
};
