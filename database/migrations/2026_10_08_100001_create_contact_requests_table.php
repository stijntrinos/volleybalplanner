<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demo, question and quote requests from the contact form of the public
 * site; handled by the platform admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('topic');
            $table->string('name');
            $table->string('club');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('teams')->nullable();
            $table->string('venues')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['handled_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_requests');
    }
};
