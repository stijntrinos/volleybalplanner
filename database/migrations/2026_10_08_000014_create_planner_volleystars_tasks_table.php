<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_volleystars_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->constrained('planner_volleystars_blocks')->cascadeOnDelete();
            $table->string('role');
            $table->foreignId('team_id')->nullable()->constrained('planner_teams')->nullOnDelete();
            $table->foreignId('court_id')->nullable()->constrained('planner_courts')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['block_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_volleystars_tasks');
    }
};
