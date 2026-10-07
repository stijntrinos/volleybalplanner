<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_nevobo_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('version_id')->constrained('planner_schedule_versions')->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('planner_matches')->cascadeOnDelete();
            $table->string('field');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->boolean('is_processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['match_id', 'field']);
            $table->index(['version_id', 'is_processed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_nevobo_changes');
    }
};
