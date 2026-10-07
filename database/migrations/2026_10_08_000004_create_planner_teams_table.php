<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('source')->default('manual');
            $table->string('default_weekday')->nullable();
            $table->time('default_time')->nullable();
            $table->foreignId('default_venue_id')->nullable()->constrained('planner_venues')->nullOnDelete();
            $table->unsignedTinyInteger('default_court')->nullable();
            $table->unsignedSmallInteger('default_match_duration')->nullable();
            $table->boolean('plays_on_two_courts')->default(false);
            $table->boolean('scores_own_match')->default(false);
            $table->boolean('provides_referee')->default(false);
            $table->boolean('nevobo_referee')->default(false);
            $table->boolean('has_duties')->default(false);
            $table->boolean('include_in_duties')->default(true);
            $table->boolean('volunteers')->default(false);
            $table->boolean('include_volunteers')->default(true);
            $table->unsignedSmallInteger('volunteer_points_used')->default(0);
            $table->unsignedSmallInteger('volunteer_points_earned')->default(0);
            $table->integer('volunteer_points_percentage')->nullable();
            $table->string('locker_room_category')->nullable();
            $table->timestamps();
            $table->unique(['club_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_teams');
    }
};
