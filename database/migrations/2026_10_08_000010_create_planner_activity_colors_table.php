<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_activity_colors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('color');
            $table->timestamps();
            $table->unique(['club_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_activity_colors');
    }
};
