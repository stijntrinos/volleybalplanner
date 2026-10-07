<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_volleystars_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('version_id')->constrained('planner_schedule_versions')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('venue_id')->constrained('planner_venues')->restrictOnDelete();
            $table->time('setup_start')->default('08:00:00');
            $table->time('setup_end')->default('12:00:00');
            $table->time('teardown_start')->default('08:30:00');
            $table->time('teardown_end')->default('12:45:00');
            $table->time('scoring_start')->default('08:45:00');
            $table->time('scoring_end')->default('12:30:00');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['version_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_volleystars_blocks');
    }
};
