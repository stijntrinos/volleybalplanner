<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subscriptions: a club gets its modules and limits from its plan instead of
 * a loose list of switched-on modules. The three plans of the pricing page
 * are created here; platform admins edit them in /admin afterwards.
 */
return new class extends Migration
{
    /** @var list<array<string, mixed>> */
    private const DEFAULTS = [
        [
            'name' => 'Basis',
            'slug' => 'basis',
            'tagline' => 'Voor kleinere verenigingen tot 15 teams in één sporthal.',
            'max_teams' => 15,
            'max_venues' => 1,
            'max_admins' => 3,
            'is_featured' => false,
            'sort_order' => 1,
            'features' => ['away_matches', 'duties', 'volunteer_points', 'trainings'],
            'highlights' => ['Thuiswedstrijden en dagrooster', 'Nachtelijke Nevobo-sync', 'FTZ-planner met vrijwilligerspunten', 'Dagschema als afbeelding en PDF', 'Publiek wedstrijdprogramma'],
        ],
        [
            'name' => 'Vereniging',
            'slug' => 'vereniging',
            'tagline' => 'Voor verenigingen tot 30 teams, in maximaal drie sporthallen.',
            'max_teams' => 30,
            'max_venues' => 3,
            'max_admins' => 6,
            'is_featured' => true,
            'sort_order' => 2,
            'features' => ['away_matches', 'duties', 'volunteer_points', 'trainings', 'change_requests', 'rss_feeds', 'volleystars', 'locker_rooms'],
            'highlights' => ['Tot drie sporthallen', 'Wijzigingsverzoeken via vaste links', 'RSS-feeds voor app en schermen', 'VolleyStars en kleedkamers', 'Eigen clubkleur en logo op exports'],
        ],
        [
            'name' => 'Groot',
            'slug' => 'groot',
            'tagline' => 'Voor verenigingen met meer dan 30 teams of toernooidagen.',
            'max_teams' => null,
            'max_venues' => null,
            'max_admins' => null,
            'is_featured' => false,
            'sort_order' => 3,
            'features' => ['away_matches', 'duties', 'volunteer_points', 'trainings', 'change_requests', 'rss_feeds', 'volleystars', 'locker_rooms', 'sitting_volleyball'],
            'highlights' => ['Onbeperkt teams en sporthallen', 'Zitvolleybal', 'Onbeperkt beheerders', 'Hulp bij de eerste import en inrichting', 'Voorrang bij support'],
        ],
    ];

    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            // Whole euros per season, excluding VAT; empty means "op aanvraag".
            $table->unsignedInteger('price')->nullable();
            $table->unsignedInteger('max_teams')->nullable();
            $table->unsignedInteger('max_venues')->nullable();
            $table->unsignedInteger('max_admins')->nullable();
            $table->json('features')->nullable();
            $table->json('highlights')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach (self::DEFAULTS as $plan) {
            DB::table('plans')->insert([
                ...$plan,
                'features' => json_encode($plan['features']),
                'highlights' => json_encode($plan['highlights']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('clubs', function (Blueprint $table): void {
            $table->foreignId('plan_id')->nullable()->after('is_active')->constrained()->nullOnDelete();
        });

        // Existing clubs keep every module they had: they get the smallest
        // plan that covers them.
        $plans = DB::table('plans')->orderBy('sort_order')->get(['id', 'features']);
        foreach (DB::table('clubs')->get(['id', 'features']) as $club) {
            $had = json_decode((string) $club->features, true) ?: [];
            $plan = $plans->first(fn (object $plan): bool => array_diff($had, json_decode((string) $plan->features, true) ?: []) === [])
                ?? $plans->last();
            DB::table('clubs')->where('id', $club->id)->update(['plan_id' => $plan->id]);
        }

        Schema::table('clubs', function (Blueprint $table): void {
            $table->dropColumn('features');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table): void {
            $table->json('features')->nullable();
        });

        foreach (DB::table('clubs')->whereNotNull('plan_id')->get(['id', 'plan_id']) as $club) {
            DB::table('clubs')->where('id', $club->id)->update([
                'features' => DB::table('plans')->where('id', $club->plan_id)->value('features'),
            ]);
        }

        Schema::table('clubs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('plan_id');
        });

        Schema::dropIfExists('plans');
    }
};
