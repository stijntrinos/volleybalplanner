<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('name');
            $table->string('type');
            $table->timestamps();
            $table->unique(['club_id', 'start_date', 'end_date', 'name', 'type'], 'planner_calendar_events_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_calendar_events');
    }
};
