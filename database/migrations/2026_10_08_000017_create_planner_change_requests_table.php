<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('planner_matches')->nullOnDelete();
            $table->foreignId('hall_duty_id')->nullable()->constrained('planner_hall_duties')->nullOnDelete();
            $table->string('match_code', 100)->nullable();
            $table->foreignId('team_id')->nullable()->constrained('planner_teams')->nullOnDelete();
            $table->string('kind', 20)->default('match');
            $table->string('duty_type', 20)->nullable();
            $table->date('duty_date')->nullable();
            $table->string('duty_time', 20)->nullable();
            $table->text('duty_location')->nullable();
            $table->text('duty_description')->nullable();
            // name, email and phone are stored encrypted, hence text.
            $table->text('name');
            $table->text('email');
            $table->text('phone')->nullable();
            $table->string('contact_preference', 20)->default('email');
            $table->string('reason', 191);
            $table->text('reason_other')->nullable();
            $table->text('explanation');
            $table->date('proposed_date')->nullable();
            $table->string('proposed_time', 5)->nullable();
            $table->string('status', 20)->default('open');
            $table->text('note')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['club_id', 'status']);
            $table->index(['club_id', 'match_code']);
            $table->index(['club_id', 'kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_change_requests');
    }
};
