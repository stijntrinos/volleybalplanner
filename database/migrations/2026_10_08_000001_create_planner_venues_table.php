<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_venues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('venue_code')->nullable();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_managed')->default(false);
            $table->boolean('has_duties')->default(false);
            $table->boolean('show_locker_rooms_on_schedule')->default(false);
            $table->boolean('for_matches')->default(false);
            $table->boolean('for_trainings')->default(true);
            $table->string('source')->default('manual');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['club_id', 'name', 'city']);
            $table->unique(['club_id', 'venue_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_venues');
    }
};
