<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_hall_duties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('version_id')->constrained('planner_schedule_versions')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            // RESTRICT on purpose: a venue or team still on duty must not silently disappear.
            $table->foreignId('team_id')->constrained('planner_teams')->restrictOnDelete();
            $table->boolean('team_auto')->default(false);
            $table->foreignId('venue_id')->constrained('planner_venues')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['version_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_hall_duties');
    }
};
