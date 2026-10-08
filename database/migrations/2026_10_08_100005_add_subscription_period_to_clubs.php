<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a club's subscription runs: a trial, a start and an end date. Empty
 * dates mean "not set"; nothing is switched off automatically, the club and
 * the platform admins get a notice instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table): void {
            $table->date('trial_ends_at')->nullable()->after('plan_id');
            $table->date('subscription_starts_at')->nullable()->after('trial_ends_at');
            $table->date('subscription_ends_at')->nullable()->after('subscription_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table): void {
            $table->dropColumn(['trial_ends_at', 'subscription_starts_at', 'subscription_ends_at']);
        });
    }
};
