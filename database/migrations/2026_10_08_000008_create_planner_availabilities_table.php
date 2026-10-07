<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('planner_venues')->cascadeOnDelete();
            $table->foreignId('court_id')->nullable()->constrained('planner_courts')->cascadeOnDelete();
            $table->string('scope');
            $table->date('date')->nullable();
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('status');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['venue_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_availabilities');
    }
};
