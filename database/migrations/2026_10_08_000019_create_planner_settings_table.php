<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('main_venue_id')->nullable()->constrained('planner_venues')->nullOnDelete();
            $table->boolean('requests_open')->default(true);
            $table->json('request_reasons')->nullable();
            $table->json('duty_request_reasons')->nullable();
            $table->date('season_start')->nullable();
            $table->date('season_end')->nullable();
            $table->date('competition_start')->nullable();
            $table->date('competition_end')->nullable();
            $table->date('training_period_start')->nullable();
            $table->date('training_period_end')->nullable();
            $table->unsignedSmallInteger('buffer_minutes')->default(15);
            $table->unsignedSmallInteger('cache_ttl_minutes')->default(15);
            $table->unsignedSmallInteger('points_referee_duty')->default(0);
            $table->unsignedSmallInteger('points_scorer_duty')->default(0);
            $table->unsignedSmallInteger('points_hall_duty')->default(0);
            $table->unsignedSmallInteger('points_volleystars')->default(0);
            $table->json('hall_duty_weekdays')->nullable();
            $table->json('hall_duty_exceptions')->nullable();
            $table->json('team_group_order')->nullable();
            $table->string('schedule_footer')->default('GRAAG MELDEN BIJ DE ZAALWACHT!');
            $table->json('duty_rules')->nullable();
            $table->boolean('admin_override_external_error')->default(true);
            $table->json('team_name_patterns')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('primary_color')->nullable()->default('#0B7F8A');
            $table->string('schedule_tagline')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_settings');
    }
};
