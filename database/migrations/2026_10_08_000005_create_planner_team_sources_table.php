<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_team_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('planner_teams')->cascadeOnDelete();
            $table->string('name');
            $table->text('url');
            $table->string('type')->default('schedule');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_succeeded_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'name', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_team_sources');
    }
};
