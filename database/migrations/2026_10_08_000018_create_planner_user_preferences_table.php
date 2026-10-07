<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_user_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('default_venue_id')->nullable()->constrained('planner_venues')->nullOnDelete();
            $table->foreignId('active_version_id')->nullable()->constrained('planner_schedule_versions')->nullOnDelete();
            $table->date('selected_date')->nullable();
            $table->json('hidden_venues')->nullable();
            $table->string('nevobo_grouping')->default('team');
            $table->boolean('show_trainings')->default(true);
            $table->boolean('duties_ask_reason')->default(true);
            $table->boolean('duties_validation_mode')->default(true);
            $table->string('overlap_policy')->default('warn');
            $table->boolean('publish_with_conflicts')->default(false);
            $table->timestamps();
            $table->unique(['club_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_user_preferences');
    }
};
