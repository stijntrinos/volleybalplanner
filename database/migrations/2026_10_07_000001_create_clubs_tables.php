<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable();
            $table->timestamps();
        });

        Schema::create('club_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['club_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_user');
        Schema::dropIfExists('clubs');
    }
};
