<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('version_id')->constrained('planner_schedule_versions')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('title');
            $table->foreignId('venue_id')->nullable()->constrained('planner_venues')->nullOnDelete();
            $table->foreignId('court_id')->nullable()->constrained('planner_courts')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['version_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_activities');
    }
};
