<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('version_id')->constrained('planner_schedule_versions')->cascadeOnDelete();
            $table->date('date')->nullable()->index();
            $table->time('time')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(105);
            $table->string('home_team_name');
            $table->string('away_team_name');
            $table->foreignId('home_team_id')->nullable()->constrained('planner_teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('planner_teams')->nullOnDelete();
            $table->string('pool')->nullable();
            $table->string('match_code');
            $table->string('region')->nullable();
            $table->string('match_type')->default('home');
            $table->foreignId('venue_id')->nullable()->constrained('planner_venues')->nullOnDelete();
            $table->foreignId('court_id')->nullable()->constrained('planner_courts')->nullOnDelete();
            $table->foreignId('second_court_id')->nullable()->constrained('planner_courts')->nullOnDelete();
            $table->date('provisional_date')->nullable();
            $table->time('provisional_time')->nullable();
            $table->unsignedSmallInteger('provisional_duration_minutes')->nullable();
            $table->foreignId('provisional_venue_id')->nullable()->constrained('planner_venues')->nullOnDelete();
            $table->foreignId('provisional_court_id')->nullable()->constrained('planner_courts')->nullOnDelete();
            $table->foreignId('provisional_second_court_id')->nullable()->constrained('planner_courts')->nullOnDelete();
            $table->string('referee')->nullable();
            $table->boolean('referee_auto')->default(false);
            $table->boolean('nevobo_referee')->default(false);
            $table->string('nevobo_status')->default('not_applicable');
            $table->string('nevobo_change_status')->default('none');
            $table->timestamp('nevobo_changed_at')->nullable();
            $table->date('nevobo_previous_date')->nullable();
            $table->time('nevobo_previous_time')->nullable();
            $table->string('nevobo_previous_venue')->nullable();
            $table->date('nevobo_new_date')->nullable();
            $table->time('nevobo_new_time')->nullable();
            $table->string('nevobo_new_venue')->nullable();
            $table->string('scorer')->nullable();
            $table->boolean('scorer_auto')->default(false);
            $table->text('notes')->nullable();
            $table->date('original_date')->nullable();
            $table->time('original_time')->nullable();
            $table->string('original_venue')->nullable();
            $table->string('original_court')->nullable();
            $table->string('change_status')->nullable();
            $table->boolean('change_requested')->default(false);
            $table->timestamp('change_requested_at')->nullable();
            $table->foreignId('change_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('away_change_status')->default('none');
            $table->date('away_new_date')->nullable();
            $table->time('away_new_time')->nullable();
            $table->string('away_new_venue')->nullable();
            $table->text('away_notes')->nullable();
            $table->boolean('is_sitting_volleyball')->default(false);
            $table->foreignId('suggested_court_id')->nullable()->constrained('planner_courts')->nullOnDelete();
            $table->unsignedSmallInteger('suggested_duration_minutes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['version_id', 'match_code']);
            $table->index(['match_type', 'date']);
            $table->index(['venue_id', 'court_id', 'date']);
            $table->index(['version_id', 'date']);
            $table->index(['version_id', 'provisional_date']);
            $table->index(['club_id', 'match_type', 'away_change_status'], 'planner_matches_away_status_index');
            $table->index(['club_id', 'is_sitting_volleyball'], 'planner_matches_sitting_volleyball_index');
            $table->index(['club_id', 'nevobo_change_status'], 'planner_matches_nevobo_change_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_matches');
    }
};
