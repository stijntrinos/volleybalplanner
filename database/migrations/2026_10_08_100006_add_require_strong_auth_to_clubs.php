<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A club can require a passkey or two-factor authentication of its members. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table): void {
            $table->boolean('require_strong_auth')->default(false)->after('committee_groups');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table): void {
            $table->dropColumn('require_strong_auth');
        });
    }
};
