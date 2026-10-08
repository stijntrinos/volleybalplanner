<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations to a club. Nobody is added to a club until they accept; the
 * link holds a random token of which only the hash is stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name');
            $table->string('role');
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_invitations');
    }
};
