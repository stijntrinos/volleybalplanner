<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_trainings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('planner_teams')->nullOnDelete();
            $table->foreignId('venue_id')->constrained('planner_venues')->restrictOnDelete();
            $table->string('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('court');
            $table->string('court_type')->nullable();
            $table->text('notes')->nullable();
            $table->string('source')->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['club_id', 'team_id', 'weekday', 'start_time', 'venue_id', 'court'], 'planner_trainings_unique');
            $table->index(['club_id', 'weekday']);
            $table->index(['venue_id', 'court', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_trainings');
    }
};
