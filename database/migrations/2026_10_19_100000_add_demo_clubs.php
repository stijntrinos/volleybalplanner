<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demo clubs made from a demo request. A demo is a normal club on a trial
 * (trial_ends_at), plus a flag so it can be told apart from a paying club
 * on a trial and pruned some time after the trial ended. The contact
 * request remembers the demo club it led to, so it is only made once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table): void {
            $table->boolean('is_demo')->default(false)->after('is_active');
        });

        Schema::table('contact_requests', function (Blueprint $table): void {
            $table->foreignId('demo_club_id')->nullable()->after('message')->constrained('clubs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contact_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('demo_club_id');
        });

        Schema::table('clubs', function (Blueprint $table): void {
            $table->dropColumn('is_demo');
        });
    }
};
