<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_courts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('planner_venues')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_training_court')->default(false);
            $table->string('source')->default('manual');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['venue_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_courts');
    }
};
