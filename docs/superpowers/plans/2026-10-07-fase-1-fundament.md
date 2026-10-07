# Fase 1 — Fundament Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Een werkende, lege multi-tenant basis: clubs, gebruikers, platform- en clubpaneel, tenancy, modules, rollen, uitnodigingen en club-jobs — waar fase 2+ de planner op bouwt.

**Architecture:** Verse Laravel 13-app. Platformcode in `app/Models`, `app/Support/Tenancy`, `app/Filament/Platform`, `app/Filament/Club`. Plannercode uitsluitend in `app/Planner`, geregistreerd via `PlannerServiceProvider` en `PlannerPlugin`. Tenancy via `CurrentClub` (scoped service) + `BelongsToClub` trait.

**Tech Stack:** PHP 8.5, Laravel 13, Filament 5, Livewire 4, filament-shield 4 + spatie/laravel-permission (teams, `club_id`), PHPUnit 13, Pint, Larastan, Rector.

**Spec:** `docs/superpowers/specs/2026-10-07-standalone-planner-design.md`

## Global Constraints

- PHP-binary: `php85` (de standaard `php` op deze machine is 8.3). Composer: `php85 "$(which composer)"`. In dit plan: `PHP=php85`, `COMPOSER='php85 "$(which composer)"'`.
- Code, database, routenamen en permissienamen in het Engels; UI-teksten Nederlands via `lang/nl/*.php`; publieke URL's Nederlands.
- Plannercode alleen in `app/Planner`; mag buiten zichzelf alleen `App\Models\Club`, `App\Models\User`, `App\Support\Tenancy\*` gebruiken.
- Elke `planner_*`-tabel krijgt `club_id` (FK `clubs`, cascadeOnDelete, index) en het model gebruikt `BelongsToClub`.
- Roles zijn globaal (`club_id` null); rol-toewijzingen zijn per club (spatie teams, `team_foreign_key = club_id`).
- Bestanden < 500 regels. Commitberichten Nederlands, eindigend met `Co-Authored-By: Claude Opus 5.5 (1M context) <noreply@anthropic.com>`.
- Elke task eindigt met: `php85 artisan test` groen, `php85 vendor/bin/pint --dirty` uitgevoerd.

**Afwijking van spec §8 fase 1:** `planner_settings` verhuist naar fase 2 (het model hoort bij de stamdata-instellingen). Spec §3.3 "ClubObserver maakt rollen aan" wordt: rollen zijn globaal en worden gesynchroniseerd door `planner:sync-roles`; spec wordt in Task 11 bijgewerkt.

## Review Focus

1. Query op een `BelongsToClub`-model zonder actieve club (artisan, job zonder club) → `NoCurrentClub`-exception, nooit alle clubs. Test in Task 4.
2. Gebruiker van club A opent `/app/{club-b}` → 404, ook als club B inactief wordt terwijl hij ingelogd is. Test in Task 7.
3. Module `volunteer_points` aan zonder `duties` (direct in DB) → `hasFeature` geeft `false`. Test in Task 3.
4. Bestaand e-mailadres uitnodigen voor een tweede club → zelfde user, extra club, geen nieuw wachtwoord. Test in Task 9.
5. Rol van een user in club A lekt naar club B (spatie team-id niet gezet) → user heeft in club B geen rol. Test in Task 6.

---

### Task 1: Skeleton en tooling

**Files:**
- Create: Laravel 13 skeleton in projectroot (behoud `docs/`, `.git/`)
- Create: `pint.json`, `phpstan.neon`, `rector.php` (kopie Aetos, aangepast), `phpunit.xml` (aangepast)
- Modify: `composer.json` (naam, scripts)

- [ ] **Step 1: Skeleton installeren**

```bash
cd /Users/stijn/Herd
php85 "$(which composer)" create-project laravel/laravel volleybalplanner-tmp "^13.0" --no-interaction
rsync -a volleybalplanner-tmp/ volleybalplanner-clean/ --exclude .git
rm -rf volleybalplanner-tmp
cd volleybalplanner-clean && herd isolate 8.5
```

- [ ] **Step 2: Packages**

```bash
php85 "$(which composer)" require filament/filament:"^5.0" bezhansalleh/filament-shield:"^4.0" spatie/laravel-permission spatie/laravel-activitylog stechstudio/filament-impersonate:"^5.0" -W
php85 "$(which composer)" require --dev larastan/larastan:"^3.7" rector/rector:"^2.2" driftingly/rector-laravel:"^2.1" barryvdh/laravel-ide-helper laravel/boost laravel/pail
php85 artisan filament:install --panels --no-interaction   # maakt AdminPanelProvider; die hernoemen we in Task 7
```

(dompdf, browsershot en backup komen in fase 5.)

- [ ] **Step 3: Tooling-configs**

Kopieer `pint.json` en `rector.php` letterlijk uit `~/Herd/aetos`. `phpstan.neon`:

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app
    level: 5
```

`phpunit.xml`: neem Aetos-versie over zonder de twee `<exclude>`-regels voor Mollie.

`composer.json`: `"name": "stijn/volleybalplanner"`, `"description": "Wedstrijdplanner voor volleybalverenigingen."`, scripts `test` en `analyse` zoals Aetos.

`.env`: `APP_NAME=Volleybalplanner`, `APP_URL=https://volleybalplanner-clean.test`, `APP_LOCALE=nl`, `APP_FALLBACK_LOCALE=nl`, `DB_CONNECTION=mysql`, `DB_DATABASE=volleybalplanner`, `DB_USERNAME=root`, `DB_PASSWORD=`, `QUEUE_CONNECTION=database`. Zelfde sleutels (zonder waarden) in `.env.example`. Maak DB: `mysql -uroot -e "CREATE DATABASE IF NOT EXISTS volleybalplanner"`.

- [ ] **Step 4: Verifiëren**

Run: `php85 artisan migrate && php85 artisan test && php85 vendor/bin/phpstan analyse`
Expected: migraties OK, voorbeeldtests PASS, phpstan 0 errors. Verwijder daarna `tests/Feature/ExampleTest.php` en `tests/Unit/ExampleTest.php`.

- [ ] **Step 5: Commit** — `git add -A && git commit -m "Zet een verse Laravel 13-installatie met Filament en tooling op"`

---

### Task 2: Club-model en gebruikers

**Files:**
- Create: `database/migrations/2026_10_07_000001_create_clubs_tables.php`, `app/Models/Club.php`, `database/factories/ClubFactory.php`
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php` (kolom `is_platform_admin`), `app/Models/User.php`, `database/factories/UserFactory.php`
- Test: `tests/Feature/Platform/ClubModelTest.php`

**Interfaces:**
- Produces: `Club` (`name`, `slug`, `is_active`, `features` array-cast, `users(): BelongsToMany`, route key `slug`), `User::clubs(): BelongsToMany`, `User::$is_platform_admin`, factory-states `ClubFactory::inactive()`, `UserFactory::platformAdmin()`.

- [ ] **Step 1: Failing test**

```php
<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_belong_to_multiple_clubs(): void
    {
        $user = User::factory()->create();
        [$a, $b] = Club::factory()->count(2)->create();

        $user->clubs()->attach([$a->id, $b->id]);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $user->clubs()->pluck('clubs.id')->all());
        $this->assertTrue($a->users->contains($user));
    }

    public function test_club_uses_slug_as_route_key_and_casts_features(): void
    {
        $club = Club::factory()->create(['slug' => 'vv-test', 'features' => ['duties']]);

        $this->assertSame('vv-test', $club->getRouteKey());
        $this->assertSame(['duties'], $club->fresh()->features);
        $this->assertTrue($club->is_active);
    }
}
```

- [ ] **Step 2:** `php85 artisan test --filter=ClubModelTest` → FAIL (`Class "App\Models\Club" not found`).

- [ ] **Step 3: Implementatie**

Migratie:

```php
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
```

In de users-migratie na `password`: `$table->boolean('is_platform_admin')->default(false);`

`app/Models/Club.php`:

```php
<?php

namespace App\Models;

use Database\Factories\ClubFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Club extends Model
{
    /** @use HasFactory<ClubFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'is_active', 'features'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'features' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
```

`ClubFactory::definition()`: `['name' => $name = fake()->unique()->company(), 'slug' => Str::slug($name), 'is_active' => true, 'features' => []]`; `inactive()` zet `is_active => false`.

`User`: voeg `is_platform_admin` toe aan casts (`boolean`), `clubs(): BelongsToMany` (`belongsToMany(Club::class)->withTimestamps()`). `UserFactory::platformAdmin()` zet `is_platform_admin => true`. `is_platform_admin` staat **niet** in `$fillable`.

- [ ] **Step 4:** `php85 artisan test --filter=ClubModelTest` → PASS.
- [ ] **Step 5: Commit** — `"Voeg clubs en het lidmaatschap van gebruikers toe"`

---

### Task 3: Modules (PlannerFeature)

**Files:**
- Create: `app/Planner/Features/PlannerFeature.php`, `lang/nl/planner.php`
- Modify: `app/Models/Club.php` (`hasFeature`)
- Test: `tests/Unit/Planner/PlannerFeatureTest.php`, uitbreiden `tests/Feature/Platform/ClubModelTest.php`

**Interfaces:**
- Produces: `enum PlannerFeature: string` met cases `AwayMatches='away_matches'`, `Duties='duties'`, `VolunteerPoints='volunteer_points'`, `Volleystars='volleystars'`, `Trainings='trainings'`, `ChangeRequests='change_requests'`, `LockerRooms='locker_rooms'`, `SittingVolleyball='sitting_volleyball'`, `RssFeeds='rss_feeds'`; `requires(): list<self>`; `getLabel(): string`; `static unmetRequirements(array $values): list<string>` (per module met ontbrekende vereiste één melding: `"{label} vereist {label vereiste}."`); `Club::hasFeature(PlannerFeature $feature): bool`.

- [ ] **Step 1: Failing tests**

```php
<?php

namespace Tests\Unit\Planner;

use App\Planner\Features\PlannerFeature;
use Tests\TestCase;

class PlannerFeatureTest extends TestCase
{
    public function test_volunteer_points_and_volleystars_require_duties(): void
    {
        $this->assertSame([PlannerFeature::Duties], PlannerFeature::VolunteerPoints->requires());
        $this->assertSame([PlannerFeature::Duties], PlannerFeature::Volleystars->requires());
        $this->assertSame([], PlannerFeature::Trainings->requires());
    }

    public function test_unmet_requirements_lists_features_missing_a_dependency(): void
    {
        $this->assertSame([], PlannerFeature::unmetRequirements(['duties', 'volunteer_points']));
        $this->assertSame(
            ['Vrijwilligerspunten vereist Diensten (fluiten, tellen, zaaldienst).'],
            PlannerFeature::unmetRequirements(['volunteer_points', 'trainings']),
        );
    }

    public function test_every_feature_has_a_dutch_label(): void
    {
        foreach (PlannerFeature::cases() as $feature) {
            $this->assertStringNotContainsString('planner.', $feature->getLabel());
        }
    }
}
```

In `ClubModelTest`:

```php
public function test_has_feature_requires_the_feature_and_its_dependencies(): void
{
    $club = Club::factory()->create(['features' => ['volunteer_points', 'trainings']]);

    $this->assertTrue($club->hasFeature(PlannerFeature::Trainings));
    $this->assertFalse($club->hasFeature(PlannerFeature::VolunteerPoints)); // duties ontbreekt
    $this->assertFalse($club->hasFeature(PlannerFeature::Duties));

    $club->update(['features' => ['duties', 'volunteer_points']]);
    $this->assertTrue($club->hasFeature(PlannerFeature::VolunteerPoints));
}

public function test_unknown_feature_values_in_the_database_are_ignored(): void
{
    $club = Club::factory()->create(['features' => ['does_not_exist', 'duties']]);

    $this->assertTrue($club->hasFeature(PlannerFeature::Duties));
}
```

- [ ] **Step 2:** `php85 artisan test --filter='PlannerFeatureTest|ClubModelTest'` → FAIL.

- [ ] **Step 3: Implementatie**

```php
<?php

namespace App\Planner\Features;

use Filament\Support\Contracts\HasLabel;

enum PlannerFeature: string implements HasLabel
{
    case AwayMatches = 'away_matches';
    case Duties = 'duties';
    case VolunteerPoints = 'volunteer_points';
    case Volleystars = 'volleystars';
    case Trainings = 'trainings';
    case ChangeRequests = 'change_requests';
    case LockerRooms = 'locker_rooms';
    case SittingVolleyball = 'sitting_volleyball';
    case RssFeeds = 'rss_feeds';

    /** @return list<self> */
    public function requires(): array
    {
        return match ($this) {
            self::VolunteerPoints, self::Volleystars => [self::Duties],
            default => [],
        };
    }

    public function getLabel(): string
    {
        return __('planner.features.'.$this->value);
    }

    /**
     * @param  array<int, string>  $values
     * @return list<string>
     */
    public static function unmetRequirements(array $values): array
    {
        $unmet = [];

        foreach ($values as $value) {
            $feature = self::tryFrom($value);

            foreach ($feature?->requires() ?? [] as $required) {
                if (! in_array($required->value, $values, true)) {
                    $unmet[] = __('planner.feature_requires', [
                        'feature' => $feature->getLabel(),
                        'required' => $required->getLabel(),
                    ]);
                }
            }
        }

        return $unmet;
    }
}
```

`Club::hasFeature`:

```php
public function hasFeature(PlannerFeature $feature): bool
{
    $enabled = $this->features ?? [];

    if (! in_array($feature->value, $enabled, true)) {
        return false;
    }

    return collect($feature->requires())->every(fn (PlannerFeature $required): bool => $this->hasFeature($required));
}
```

`lang/nl/planner.php`:

```php
<?php

return [
    'features' => [
        'away_matches' => 'Uitwedstrijden',
        'duties' => 'Diensten (fluiten, tellen, zaaldienst)',
        'volunteer_points' => 'Vrijwilligerspunten',
        'volleystars' => 'VolleyStars',
        'trainings' => 'Trainingen',
        'change_requests' => 'Wijzigingsverzoeken',
        'locker_rooms' => 'Kleedkamers',
        'sitting_volleyball' => 'Zitvolleybal',
        'rss_feeds' => 'RSS-feeds',
    ],
    'feature_requires' => ':feature vereist :required.',
];
```

- [ ] **Step 4:** tests → PASS.
- [ ] **Step 5: Commit** — `"Voeg de planner-modules toe die per club aan of uit kunnen"`

---

### Task 4: CurrentClub en BelongsToClub

**Files:**
- Create: `app/Support/Tenancy/CurrentClub.php`, `app/Support/Tenancy/BelongsToClub.php`, `app/Support/Tenancy/NoCurrentClub.php`
- Modify: `app/Providers/AppServiceProvider.php` (scoped binding)
- Test: `tests/Feature/Platform/TenancyTest.php` (met een testtabel `tenancy_probes` in `setUp`)

**Interfaces:**
- Produces: `CurrentClub` (scoped): `get(): ?Club`, `require(): Club` (throws `NoCurrentClub`), `id(): int` (via require), `set(?Club $club): void`, `run(Club $club, callable $callback): mixed`. Trait `BelongsToClub`: global scope `'club'`, auto-fill `club_id`, `club(): BelongsTo`. Volgorde van resolutie in `get()`: expliciet gezette club → `Filament::getTenant()` als dat een `Club` is → `null`.

- [ ] **Step 1: Failing test**

```php
<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Support\Tenancy\BelongsToClub;
use App\Support\Tenancy\CurrentClub;
use App\Support\Tenancy\NoCurrentClub;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenancyProbe extends Model
{
    use BelongsToClub;

    protected $table = 'tenancy_probes';

    protected $fillable = ['label', 'club_id'];
}

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tenancy_probes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->timestamps();
        });
    }

    public function test_records_are_scoped_to_the_current_club_and_filled_on_create(): void
    {
        [$a, $b] = Club::factory()->count(2)->create();
        $current = app(CurrentClub::class);

        $current->run($a, fn () => TenancyProbe::create(['label' => 'a']));
        $current->run($b, fn () => TenancyProbe::create(['label' => 'b']));

        $this->assertSame(['a'], $current->run($a, fn () => TenancyProbe::pluck('label')->all()));
        $this->assertSame(['b'], $current->run($b, fn () => TenancyProbe::pluck('label')->all()));
        $this->assertSame($b->id, TenancyProbe::withoutGlobalScope('club')->where('label', 'b')->value('club_id'));
    }

    public function test_querying_without_a_current_club_throws(): void
    {
        $this->expectException(NoCurrentClub::class);

        TenancyProbe::count();
    }

    public function test_run_restores_the_previous_club_even_after_an_exception(): void
    {
        [$a, $b] = Club::factory()->count(2)->create();
        $current = app(CurrentClub::class);
        $current->set($a);

        try {
            $current->run($b, fn () => throw new \RuntimeException('boom'));
        } catch (\RuntimeException) {
        }

        $this->assertTrue($current->get()->is($a));
    }

    public function test_an_explicit_club_id_on_create_is_not_overwritten(): void
    {
        [$a, $b] = Club::factory()->count(2)->create();

        $probe = app(CurrentClub::class)->run($a, fn () => TenancyProbe::create(['label' => 'x', 'club_id' => $b->id]));

        $this->assertSame($b->id, $probe->club_id);
    }
}
```

- [ ] **Step 2:** `php85 artisan test --filter=TenancyTest` → FAIL.

- [ ] **Step 3: Implementatie**

```php
<?php

namespace App\Support\Tenancy;

use RuntimeException;

class NoCurrentClub extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Er is geen actieve club. Gebruik CurrentClub::run() of withoutGlobalScope(\'club\').');
    }
}
```

```php
<?php

namespace App\Support\Tenancy;

use App\Models\Club;
use Filament\Facades\Filament;

/**
 * De enige bron voor "welke club is actief". Paneel: Filament-tenant;
 * publieke routes: ResolveClubFromRoute; jobs en commands: run().
 */
class CurrentClub
{
    private ?Club $club = null;

    public function get(): ?Club
    {
        if ($this->club !== null) {
            return $this->club;
        }

        $tenant = Filament::getTenant();

        return $tenant instanceof Club ? $tenant : null;
    }

    public function require(): Club
    {
        return $this->get() ?? throw new NoCurrentClub;
    }

    public function id(): int
    {
        return $this->require()->getKey();
    }

    public function set(?Club $club): void
    {
        $this->club = $club;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(Club $club, callable $callback): mixed
    {
        $previous = $this->club;
        $this->club = $club;

        try {
            return $callback();
        } finally {
            $this->club = $previous;
        }
    }
}
```

```php
<?php

namespace App\Support\Tenancy;

use App\Models\Club;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToClub
{
    protected static function bootBelongsToClub(): void
    {
        static::addGlobalScope('club', function (Builder $query): void {
            $query->where($query->qualifyColumn('club_id'), app(CurrentClub::class)->id());
        });

        static::creating(function (Model $model): void {
            if (! $model->getAttribute('club_id')) {
                $model->setAttribute('club_id', app(CurrentClub::class)->id());
            }
        });
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
```

`AppServiceProvider::register()`: `$this->app->scoped(CurrentClub::class);`

- [ ] **Step 4:** tests → PASS.
- [ ] **Step 5: Commit** — `"Scheid data per club met CurrentClub en BelongsToClub"`

---

### Task 5: Publieke club-routes en module-middleware

**Files:**
- Create: `app/Support/Tenancy/ResolveClubFromRoute.php`, `app/Planner/Http/Middleware/EnsureClubFeature.php`, `app/Planner/PlannerServiceProvider.php`, `routes/planner.php`
- Modify: `bootstrap/app.php` (aliases), `bootstrap/providers.php`
- Test: `tests/Feature/Platform/PublicClubRoutesTest.php`

**Interfaces:**
- Produces: middleware-aliassen `club` (ResolveClubFromRoute; leest routeparameter `club` als slug, 404 bij onbekend/inactief, zet `CurrentClub`, vervangt parameter door `Club`) en `feature` (`feature:duties`, 404 als module uit). `routes/planner.php` wordt geladen door `PlannerServiceProvider` met prefix `{club}`, middleware `['web', 'club']`, naamprefix `planner.public.`; nog zonder routes (fase 7 vult hem).

- [ ] **Step 1: Failing test** (registreert eigen testroutes)

```php
<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicClubRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'club'])->prefix('{club}')->group(function (): void {
            Route::get('probe', fn (Club $club) => $club->slug.':'.app(CurrentClub::class)->id());
            Route::get('duties-probe', fn () => 'ok')->middleware('feature:duties');
        });
    }

    public function test_known_active_club_is_resolved(): void
    {
        $club = Club::factory()->create(['slug' => 'vv-a']);

        $this->get('/vv-a/probe')->assertOk()->assertSeeText('vv-a:'.$club->id);
    }

    public function test_unknown_or_inactive_club_gives_404(): void
    {
        Club::factory()->inactive()->create(['slug' => 'slapend']);

        $this->get('/bestaat-niet/probe')->assertNotFound();
        $this->get('/slapend/probe')->assertNotFound();
    }

    public function test_disabled_feature_gives_404(): void
    {
        Club::factory()->create(['slug' => 'zonder', 'features' => []]);
        Club::factory()->create(['slug' => 'met', 'features' => ['duties']]);

        $this->get('/zonder/duties-probe')->assertNotFound();
        $this->get('/met/duties-probe')->assertOk();
    }
}
```

- [ ] **Step 2:** → FAIL (alias `club` onbekend).

- [ ] **Step 3: Implementatie**

```php
<?php

namespace App\Support\Tenancy;

use App\Models\Club;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveClubFromRoute
{
    public function __construct(private readonly CurrentClub $currentClub) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('club');
        $club = is_string($slug) ? Club::query()->where('slug', $slug)->where('is_active', true)->first() : null;

        abort_if($club === null, 404);

        $this->currentClub->set($club);
        $request->route()->setParameter('club', $club);

        return $next($request);
    }
}
```

```php
<?php

namespace App\Planner\Http\Middleware;

use App\Planner\Features\PlannerFeature;
use App\Support\Tenancy\CurrentClub;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClubFeature
{
    public function __construct(private readonly CurrentClub $currentClub) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless($this->currentClub->require()->hasFeature(PlannerFeature::from($feature)), 404);

        return $next($request);
    }
}
```

`bootstrap/app.php` → `->withMiddleware(function (Middleware $middleware): void { $middleware->alias(['club' => ResolveClubFromRoute::class, 'feature' => EnsureClubFeature::class]); })`

`PlannerServiceProvider::boot()`:

```php
public function boot(): void
{
    Route::middleware(['web', 'club'])
        ->prefix('{club}')
        ->name('planner.public.')
        ->group(base_path('routes/planner.php'));
}
```

`routes/planner.php` bevat alleen `<?php` + `use Illuminate\Support\Facades\Route;`. Registreer de provider in `bootstrap/providers.php`. Let op: de `{club}`-prefix-groep moet **na** de web-routes geladen worden zodat `/admin` en `/app` niet als clubslug matchen — Filament registreert zijn routes eerder dan `boot()` van app-providers; controleer met `php85 artisan route:list` dat `admin/login` niet naar de clubgroep gaat. Voeg bovendien een reserved-slug-regel toe in Task 8 (`admin`, `app`, `login`, `livewire`, `filament`, `storage`, `up`).

- [ ] **Step 4:** tests → PASS.
- [ ] **Step 5: Commit** — `"Herken de club in publieke URL's en blokkeer uitgeschakelde modules"`

---

### Task 6: Rollen en permissies per club

**Files:**
- Create: `app/Planner/Support/PlannerPermissions.php`, `app/Planner/Support/ClubRole.php`, `app/Planner/Policies/PlannerPolicy.php`, `app/Planner/Console/SyncRolesCommand.php`
- Modify: `config/permission.php` (`teams => true`, `team_foreign_key => 'club_id'`), gepubliceerde permission-migratie, `config/filament-shield.php` (`tenant_model => Club::class`), `app/Models/User.php` (`HasRoles`), `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Platform/ClubRolesTest.php`

**Interfaces:**
- Produces: `enum ClubRole: string { ClubAdmin='club_admin'; PlanningCommittee='planning_committee' }` met `getLabel()` ("Clubbeheerder", "Zaalcommissie"). `PlannerPermissions::MODELS/PAGES/WIDGETS` (lege lijsten; fases voegen namen toe), `PlannerPermissions::ACTIONS`, `PlannerPermissions::all(): list<string>`. Command `planner:sync-roles` (idempotent; maakt alle permissies + beide rollen globaal, beide rollen krijgen `all()`). `PlannerPolicy` (abstract, `permissionModel(): string`, zelfde methodes als Aetos). Helper `User::assignClubRole(Club $club, ClubRole $role): void` en `User::hasClubRole(Club $club, ClubRole $role): bool`.

- [ ] **Step 1: Packages publiceren**

```bash
php85 artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php85 artisan vendor:publish --tag=filament-shield-config
```

Zet in `config/permission.php`: `'teams' => true`, `'team_foreign_key' => 'club_id'`. In de gepubliceerde migratie hoeft niets te veranderen (die leest de config).

- [ ] **Step 2: Failing test**

```php
<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Models\User;
use App\Planner\Support\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClubRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_roles_is_idempotent_and_creates_both_roles(): void
    {
        $this->artisan('planner:sync-roles')->assertSuccessful();
        $this->artisan('planner:sync-roles')->assertSuccessful();

        $this->assertSame(2, Role::count());
        $this->assertNotNull(Role::findByName('club_admin'));
        $this->assertNotNull(Role::findByName('planning_committee'));
    }

    public function test_a_role_in_one_club_does_not_leak_to_another(): void
    {
        $this->artisan('planner:sync-roles');
        [$a, $b] = Club::factory()->count(2)->create();
        $user = User::factory()->create();

        $user->assignClubRole($a, ClubRole::ClubAdmin);

        $this->assertTrue($user->hasClubRole($a, ClubRole::ClubAdmin));
        $this->assertFalse($user->hasClubRole($b, ClubRole::ClubAdmin));
    }
}
```

- [ ] **Step 3:** → FAIL.

- [ ] **Step 4: Implementatie**

```php
<?php

namespace App\Planner\Support;

final class PlannerPermissions
{
    public const ACTIONS = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'];

    /** Shield-modelnamen; elke fase voegt zijn modellen toe. @var list<string> */
    public const MODELS = [];

    /** @var list<string> */
    public const PAGES = [];

    /** @var list<string> */
    public const WIDGETS = [];

    /** @return list<string> */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::MODELS as $model) {
            foreach (self::ACTIONS as $action) {
                $permissions[] = "{$action}:{$model}";
            }
        }

        foreach ([...self::PAGES, ...self::WIDGETS] as $component) {
            $permissions[] = "View:{$component}";
        }

        return $permissions;
    }
}
```

`ClubRole` enum met `HasLabel`, labels via `__('planner.roles.'.$this->value)`; voeg aan `lang/nl/planner.php` toe: `'roles' => ['club_admin' => 'Clubbeheerder', 'planning_committee' => 'Zaalcommissie']`.

`SyncRolesCommand` (`planner:sync-roles`, beschrijving "Maak de planner-permissies en -rollen aan of werk ze bij"):

```php
public function handle(PermissionRegistrar $registrar): int
{
    $registrar->forgetCachedPermissions();
    setPermissionsTeamId(null);

    $permissions = collect(PlannerPermissions::all())
        ->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

    foreach (ClubRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web')->syncPermissions($permissions);
    }

    $this->info('Planner-rollen gesynchroniseerd.');

    return self::SUCCESS;
}
```

Registreer het command in `PlannerServiceProvider::boot()` via `$this->commands([SyncRolesCommand::class])` (binnen `if ($this->app->runningInConsole())`).

`User`:

```php
public function assignClubRole(Club $club, ClubRole $role): void
{
    $this->withClubTeam($club, fn () => $this->syncRoles([$role->value]));
}

public function hasClubRole(Club $club, ClubRole $role): bool
{
    return $this->withClubTeam($club, fn (): bool => $this->unsetRelation('roles')->hasRole($role->value));
}

/**
 * @template T
 *
 * @param  callable(): T  $callback
 * @return T
 */
private function withClubTeam(Club $club, callable $callback): mixed
{
    $previous = getPermissionsTeamId();
    setPermissionsTeamId($club->getKey());

    try {
        return $callback();
    } finally {
        setPermissionsTeamId($previous);
        $this->unsetRelation('roles')->unsetRelation('permissions');
    }
}
```

`PlannerPolicy`: kopieer `~/Herd/aetos/app/Policies/Wedstrijdzaken/PlannerPolicy.php` naar namespace `App\Planner\Policies`; vertaal het docblock naar Engels ("Model name as used in Shield permission names, e.g. 'Team' for 'ViewAny:Team'.").

`DatabaseSeeder::run()`: `$this->call(...)` niet nodig — gebruik `Artisan::call('planner:sync-roles')`.

- [ ] **Step 5:** tests → PASS.
- [ ] **Step 6: Commit** — `"Voeg clubrollen toe die per club worden toegekend"`

---

### Task 7: Platform- en clubpaneel

**Files:**
- Delete: `app/Providers/Filament/AdminPanelProvider.php` (uit Task 1)
- Create: `app/Providers/Filament/PlatformPanelProvider.php`, `app/Providers/Filament/ClubPanelProvider.php`, `app/Planner/PlannerPlugin.php`
- Modify: `app/Models/User.php` (FilamentUser, HasTenants, HasDefaultTenant), `bootstrap/providers.php`
- Test: `tests/Feature/Platform/PanelAccessTest.php`

**Interfaces:**
- Produces: panel-id's `platform` (pad `admin`, geen tenancy) en `club` (pad `app`, `->tenant(Club::class, slugAttribute: 'slug', ownershipRelationship: 'club')`, `->tenantMiddleware([SyncShieldTenant::class], isPersistent: true)`, `->plugin(PlannerPlugin::make())`). `PlannerPlugin implements Plugin` met `getId(): 'planner'`, `register(Panel)` (fases voegen `->resources/pages/widgets` toe), `boot(Panel)`, en statische `scheduleFooterUsing(?Closure)` / `renderScheduleFooter(): ?Htmlable` (spec §7).
- Gebruikersregels: `canAccessPanel('platform')` ⇔ `is_platform_admin`; `canAccessPanel('club')` ⇔ heeft minstens één actieve club; `getTenants()` = actieve clubs; `canAccessTenant($club)` ⇔ lid én club actief (vers uit DB).

- [ ] **Step 1: Failing test**

```php
<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_platform_admins_reach_the_platform_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->platformAdmin()->create())->get('/admin')->assertOk();
    }

    public function test_member_reaches_own_club_but_not_another(): void
    {
        [$own, $other] = Club::factory()->count(2)->create();
        $user = User::factory()->create();
        $user->clubs()->attach($own);

        $this->actingAs($user)->get("/app/{$own->slug}")->assertOk();
        $this->actingAs($user)->get("/app/{$other->slug}")->assertNotFound();
    }

    public function test_deactivated_club_is_closed_for_logged_in_members(): void
    {
        $club = Club::factory()->create();
        $user = User::factory()->create();
        $user->clubs()->attach($club);
        $this->actingAs($user)->get("/app/{$club->slug}")->assertOk();

        $club->update(['is_active' => false]);

        $this->actingAs($user)->get("/app/{$club->slug}")->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $club = Club::factory()->create();

        $this->get("/app/{$club->slug}")->assertRedirect('/app/login');
    }
}
```

- [ ] **Step 2:** → FAIL.

- [ ] **Step 3: Implementatie**

Genereer: `php85 artisan make:filament-panel platform` en `php85 artisan make:filament-panel club`; verwijder `AdminPanelProvider` en haal hem uit `bootstrap/providers.php`.

`PlatformPanelProvider::panel()`: `->default()->id('platform')->path('admin')->login()->passwordReset()->brandName('Volleybalplanner Platform')->colors(['primary' => Color::Indigo])->discoverResources(in: app_path('Filament/Platform/Resources'), for: 'App\Filament\Platform\Resources')->discoverPages(in: app_path('Filament/Platform/Pages'), for: 'App\Filament\Platform\Pages')->pages([Dashboard::class])` + standaard middleware/authMiddleware zoals de gegenereerde provider.

`ClubPanelProvider::panel()`: `->id('club')->path('app')->login()->passwordReset()->brandName('Volleybalplanner')->colors(['primary' => Color::Sky])->tenant(Club::class, slugAttribute: 'slug', ownershipRelationship: 'club')->tenantMiddleware([SyncShieldTenant::class], isPersistent: true)->discoverResources(in: app_path('Filament/Club/Resources'), for: 'App\Filament\Club\Resources')->pages([Dashboard::class])->plugin(PlannerPlugin::make())` + standaard middleware.

`PlannerPlugin`:

```php
<?php

namespace App\Planner;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class PlannerPlugin implements Plugin
{
    private static ?Closure $scheduleFooter = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'planner';
    }

    public function register(Panel $panel): void
    {
        // Fases 2–6 registreren hier hun clusters, resources, pages en widgets.
    }

    public function boot(Panel $panel): void {}

    /**
     * Extension point for the day schedule and exports (e.g. a sponsor row
     * provided by a separate package). See spec §7.
     */
    public static function scheduleFooterUsing(?Closure $renderer): void
    {
        self::$scheduleFooter = $renderer;
    }

    public static function renderScheduleFooter(): ?Htmlable
    {
        return self::$scheduleFooter ? (self::$scheduleFooter)() : null;
    }
}
```

`User implements FilamentUser, HasTenants, HasDefaultTenant`:

```php
public function canAccessPanel(Panel $panel): bool
{
    return match ($panel->getId()) {
        'platform' => $this->is_platform_admin,
        'club' => $this->clubs()->where('is_active', true)->exists(),
        default => false,
    };
}

public function getTenants(Panel $panel): Collection
{
    return $this->clubs()->where('is_active', true)->orderBy('name')->get();
}

public function canAccessTenant(Model $tenant): bool
{
    return $tenant instanceof Club
        && $this->clubs()->whereKey($tenant->getKey())->where('is_active', true)->exists();
}

public function getDefaultTenant(Panel $panel): ?Model
{
    return $this->getTenants($panel)->first();
}
```

Let op: een platformbeheerder zonder clubs krijgt in `/app` een 403; dat is gewenst (hij gebruikt impersonate).

- [ ] **Step 4:** tests → PASS. Controleer ook handmatig `php85 artisan route:list --path=admin` en `--path=app`.
- [ ] **Step 5: Commit** — `"Voeg het platformpaneel en het clubpaneel met tenancy toe"`

---

### Task 8: Clubbeheer in het platformpaneel

**Files:**
- Create: `app/Filament/Platform/Resources/Clubs/ClubResource.php` + `Pages/{ListClubs,CreateClub,EditClub}.php` + `Schemas/ClubForm.php` + `Tables/ClubsTable.php` + `RelationManagers/UsersRelationManager.php`
- Create: `app/Support/Tenancy/ReservedSlugs.php`
- Test: `tests/Feature/Platform/ClubResourceTest.php`

**Interfaces:**
- Consumes: `PlannerFeature::unmetRequirements()`, `Club`.
- Produces: `ReservedSlugs::ALL = ['admin', 'app', 'login', 'logout', 'livewire', 'filament', 'storage', 'up', 'build', 'vendor']`. Formvelden `name`, `slug` (uniek, regex `^[a-z0-9-]+$`, niet in `ReservedSlugs::ALL`), `is_active`, `features` (CheckboxList uit `PlannerFeature`).

- [ ] **Step 1:** `php85 artisan make:filament-resource Club --panel=platform --generate` en verplaats indien nodig naar de mappen hierboven.

- [ ] **Step 2: Failing test**

```php
<?php

namespace Tests\Feature\Platform;

use App\Filament\Platform\Resources\Clubs\Pages\CreateClub;
use App\Models\Club;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClubResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->platformAdmin()->create());
        Filament::setCurrentPanel('platform');
    }

    public function test_platform_admin_creates_a_club_with_features(): void
    {
        Livewire::test(CreateClub::class)
            ->fillForm(['name' => 'VV Test', 'slug' => 'vv-test', 'is_active' => true, 'features' => ['duties', 'volunteer_points']])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['duties', 'volunteer_points'], Club::firstWhere('slug', 'vv-test')->features);
    }

    public function test_feature_dependencies_are_enforced(): void
    {
        Livewire::test(CreateClub::class)
            ->fillForm(['name' => 'VV Test', 'slug' => 'vv-test', 'features' => ['volunteer_points']])
            ->call('create')
            ->assertHasFormErrors(['features']);
    }

    public function test_reserved_and_malformed_slugs_are_rejected(): void
    {
        foreach (['admin', 'app', 'VV Test', 'vv_test'] as $slug) {
            Livewire::test(CreateClub::class)
                ->fillForm(['name' => 'X', 'slug' => $slug])
                ->call('create')
                ->assertHasFormErrors(['slug']);
        }
    }
}
```

- [ ] **Step 3:** → FAIL.

- [ ] **Step 4: Implementatie**

`ClubForm::configure(Schema $schema)`:

```php
return $schema->components([
    TextInput::make('name')->label('Naam')->required()->maxLength(255)
        ->live(onBlur: true)
        ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => $get('slug') ? null : $set('slug', Str::slug((string) $state))),
    TextInput::make('slug')->label('Slug (URL)')->required()->maxLength(64)
        ->regex('/^[a-z0-9-]+$/')
        ->notIn(ReservedSlugs::ALL)
        ->unique(ignoreRecord: true),
    Toggle::make('is_active')->label('Actief')->default(true),
    CheckboxList::make('features')->label('Modules')
        ->options(PlannerFeature::class)
        ->columns(2)
        ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            $unmet = PlannerFeature::unmetRequirements((array) $value);
            if ($unmet !== []) {
                $fail(implode(' ', $unmet));
            }
        }]),
]);
```

`ClubsTable`: kolommen `name` (searchable), `slug`, `is_active` (IconColumn boolean), `users_count` (`->counts('users')`), `created_at`; EditAction.

`UsersRelationManager` (relatie `users`): kolommen `name`, `email`; header-action `InviteClubAdmin` (Task 9); record-action `Impersonate::make()->redirectTo(fn () => "/app/{$this->getOwnerRecord()->slug}")`; `DetachAction` met label "Verwijderen uit club".

- [ ] **Step 5:** tests → PASS.
- [ ] **Step 6: Commit** — `"Laat de platformbeheerder clubs en hun modules beheren"`

---

### Task 9: Uitnodigingen en gebruikersbeheer per club

**Files:**
- Create: `app/Support/Tenancy/ClubInvitations.php`, `app/Notifications/ClubInvitation.php`, `app/Filament/Club/Resources/ClubUsers/ClubUserResource.php` + `Pages/ListClubUsers.php`
- Modify: `app/Filament/Platform/Resources/Clubs/RelationManagers/UsersRelationManager.php` (invite-action)
- Test: `tests/Feature/Platform/ClubInvitationsTest.php`, `tests/Feature/Platform/ClubUserResourceTest.php`

**Interfaces:**
- Produces: `ClubInvitations::invite(Club $club, string $name, string $email, ClubRole $role): User` — maakt user aan als e-mail onbekend (random wachtwoord), koppelt aan club (idempotent), kent rol toe, stuurt `ClubInvitation`-notificatie. Nieuwe user: notificatie bevat wachtwoord-instellen-URL (`Filament::getPanel('club')->getResetPasswordUrl($token, $user)`); bestaande user: login-URL `/app/{slug}`. E-mail wordt lowercased en getrimd.
- `ClubUserResource` (clubpaneel, model `User`, `$isScopedToTenant = false`, query = leden van huidige club): alleen zichtbaar/toegankelijk voor `ClubRole::ClubAdmin` in die club; actions: uitnodigen (naam, e-mail, rol), rol wijzigen, verwijderen uit club (niet jezelf).

- [ ] **Step 1: Failing tests**

```php
<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Models\User;
use App\Notifications\ClubInvitation;
use App\Planner\Support\ClubRole;
use App\Support\Tenancy\ClubInvitations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClubInvitationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('planner:sync-roles');
        Notification::fake();
    }

    public function test_inviting_a_new_email_creates_a_member_with_role(): void
    {
        $club = Club::factory()->create();

        $user = app(ClubInvitations::class)->invite($club, 'Jan', '  Jan@Example.nl ', ClubRole::ClubAdmin);

        $this->assertSame('jan@example.nl', $user->email);
        $this->assertTrue($user->clubs->contains($club));
        $this->assertTrue($user->hasClubRole($club, ClubRole::ClubAdmin));
        Notification::assertSentTo($user, ClubInvitation::class, fn (ClubInvitation $n) => $n->isNewUser);
    }

    public function test_inviting_an_existing_user_to_a_second_club_keeps_password(): void
    {
        [$a, $b] = Club::factory()->count(2)->create();
        $user = User::factory()->create(['email' => 'piet@example.nl']);
        $hash = $user->password;
        $user->clubs()->attach($a);

        $same = app(ClubInvitations::class)->invite($b, 'Piet', 'piet@example.nl', ClubRole::PlanningCommittee);

        $this->assertTrue($same->is($user));
        $this->assertSame($hash, $same->fresh()->password);
        $this->assertSame(2, $same->clubs()->count());
        Notification::assertSentTo($user, ClubInvitation::class, fn (ClubInvitation $n) => ! $n->isNewUser);
    }

    public function test_inviting_twice_does_not_duplicate_membership(): void
    {
        $club = Club::factory()->create();
        $invitations = app(ClubInvitations::class);

        $invitations->invite($club, 'Jan', 'jan@example.nl', ClubRole::ClubAdmin);
        $invitations->invite($club, 'Jan', 'jan@example.nl', ClubRole::PlanningCommittee);

        $user = User::firstWhere('email', 'jan@example.nl');
        $this->assertSame(1, $user->clubs()->count());
        $this->assertTrue($user->hasClubRole($club, ClubRole::PlanningCommittee));
        $this->assertFalse($user->hasClubRole($club, ClubRole::ClubAdmin));
    }
}
```

```php
<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Models\User;
use App\Planner\Support\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubUserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_club_admins_see_user_management(): void
    {
        $this->artisan('planner:sync-roles');
        $club = Club::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $club->users()->attach([$admin->id, $member->id]);
        $admin->assignClubRole($club, ClubRole::ClubAdmin);
        $member->assignClubRole($club, ClubRole::PlanningCommittee);

        $this->actingAs($admin)->get("/app/{$club->slug}/club-users")->assertOk()->assertSee($member->email);
        $this->actingAs($member)->get("/app/{$club->slug}/club-users")->assertForbidden();
    }

    public function test_list_only_contains_members_of_the_current_club(): void
    {
        $this->artisan('planner:sync-roles');
        [$a, $b] = Club::factory()->count(2)->create();
        $admin = User::factory()->create();
        $outsider = User::factory()->create();
        $a->users()->attach($admin);
        $b->users()->attach($outsider);
        $admin->assignClubRole($a, ClubRole::ClubAdmin);

        $this->actingAs($admin)->get("/app/{$a->slug}/club-users")->assertDontSee($outsider->email);
    }
}
```

- [ ] **Step 2:** → FAIL.

- [ ] **Step 3: Implementatie**

```php
<?php

namespace App\Support\Tenancy;

use App\Models\Club;
use App\Models\User;
use App\Notifications\ClubInvitation;
use App\Planner\Support\ClubRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ClubInvitations
{
    public function invite(Club $club, string $name, string $email, ClubRole $role): User
    {
        $email = Str::lower(trim($email));

        [$user, $isNew] = DB::transaction(function () use ($club, $name, $email, $role): array {
            $user = User::firstWhere('email', $email);
            $isNew = $user === null;

            $user ??= User::create(['name' => $name, 'email' => $email, 'password' => Str::random(40)]);
            $user->clubs()->syncWithoutDetaching([$club->getKey()]);
            $user->assignClubRole($club, $role);

            return [$user, $isNew];
        });

        $url = $isNew
            ? Filament::getPanel('club')->getResetPasswordUrl(Password::broker()->createToken($user), $user)
            : url("/app/{$club->slug}");

        $user->notify(new ClubInvitation($club, $url, $isNew));

        return $user;
    }
}
```

(`ClubRole` staat in `App\Planner\Support`; dat `App\Support\Tenancy` die gebruikt is toegestaan — de regel is alleen dat `app/Planner` niet naar buiten grijpt.)

`ClubInvitation` (Notification, `ShouldQueue`, `via: ['mail']`), constructor `public Club $club, public string $url, public bool $isNewUser`; mail:
- nieuw: onderwerp "Uitnodiging voor {club} in Volleybalplanner", regel "Je bent uitgenodigd voor {club}. Stel je wachtwoord in om te beginnen.", actie "Wachtwoord instellen".
- bestaand: onderwerp "Je hebt toegang tot {club}", actie "Naar {club}".

`ClubUserResource`: `protected static ?string $model = User::class; protected static bool $isScopedToTenant = false; protected static ?string $slug = 'club-users'; navigationLabel 'Gebruikers'`;

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->whereHas('clubs', fn (Builder $q) => $q->whereKey(Filament::getTenant()->getKey()));
}

public static function canAccess(): bool
{
    $club = Filament::getTenant();

    return $club instanceof Club && auth()->user()?->hasClubRole($club, ClubRole::ClubAdmin) === true;
}
```

Tabel: `name`, `email`, kolom "Rol" (`getStateUsing` → label van de clubrol). Header-action "Uitnodigen" (form: naam, e-mail, Select rol uit `ClubRole`) → `ClubInvitations::invite`. Record-actions "Rol wijzigen" en "Verwijderen uit club" (`->hidden(fn (User $record) => $record->is(auth()->user()))`; detach + `syncRoles([])` binnen club-team).

Platform `UsersRelationManager`: header-action "Beheerder uitnodigen" (naam, e-mail) → `invite($this->getOwnerRecord(), ..., ClubRole::ClubAdmin)`.

- [ ] **Step 4:** tests → PASS.
- [ ] **Step 5: Commit** — `"Nodig gebruikers per club uit en laat clubbeheerders leden beheren"`

---

### Task 10: Club-jobs en queues

**Files:**
- Create: `config/planner.php`, `app/Support/Tenancy/ClubQueue.php`, `app/Support/Tenancy/ClubJob.php`
- Test: `tests/Feature/Platform/ClubJobTest.php`

**Interfaces:**
- Produces: `config('planner.queue.mode')` (`shared|per_club`, env `PLANNER_QUEUE_MODE`, default `shared`), `config('planner.queue.shared')` (default `planner`). `ClubQueue::for(Club $club): string`. `abstract class ClubJob implements ShouldQueue` met `public Club $club`, `$timeout = 600`, `middleware()` (WithoutOverlapping per club + jobklasse), `handle(CurrentClub)` die `handleForClub()` binnen `CurrentClub::run` aanroept en `Context::add('club_id', …)` zet. Subklassen implementeren `abstract protected function handleForClub(): void`.

- [ ] **Step 1: Failing test**

```php
<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Support\Tenancy\ClubJob;
use App\Support\Tenancy\ClubQueue;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

class ProbeClubJob extends ClubJob
{
    public static ?int $seenClubId = null;

    protected function handleForClub(): void
    {
        self::$seenClubId = app(CurrentClub::class)->id();
    }
}

class ClubJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_name_follows_config(): void
    {
        $club = Club::factory()->create(['slug' => 'vv-a']);

        config(['planner.queue.mode' => 'shared']);
        $this->assertSame('planner', ClubQueue::for($club));

        config(['planner.queue.mode' => 'per_club']);
        $this->assertSame('club-vv-a', ClubQueue::for($club));
    }

    public function test_job_runs_inside_its_club_and_restores_afterwards(): void
    {
        $club = Club::factory()->create();

        ProbeClubJob::dispatch($club);

        $this->assertSame($club->id, ProbeClubJob::$seenClubId);
        $this->assertNull(app(CurrentClub::class)->get());
    }

    public function test_job_is_put_on_the_club_queue_and_does_not_overlap(): void
    {
        config(['planner.queue.mode' => 'per_club']);
        $club = Club::factory()->create(['slug' => 'vv-b']);

        $job = new ProbeClubJob($club);

        $this->assertSame('club-vv-b', $job->queue);
        $this->assertInstanceOf(WithoutOverlapping::class, $job->middleware()[0]);
    }
}
```

- [ ] **Step 2:** → FAIL.

- [ ] **Step 3: Implementatie**

`config/planner.php`:

```php
<?php

return [
    'queue' => [
        // 'shared': all clubs on one queue; 'per_club': queue "club-{slug}" for a worker per club.
        'mode' => env('PLANNER_QUEUE_MODE', 'shared'),
        'shared' => env('PLANNER_QUEUE', 'planner'),
    ],
];
```

```php
<?php

namespace App\Support\Tenancy;

use App\Models\Club;

final class ClubQueue
{
    public static function for(Club $club): string
    {
        return config('planner.queue.mode') === 'per_club'
            ? 'club-'.$club->slug
            : (string) config('planner.queue.shared', 'planner');
    }
}
```

```php
<?php

namespace App\Support\Tenancy;

use App\Models\Club;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Context;

abstract class ClubJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public Club $club)
    {
        $this->onQueue(ClubQueue::for($club));
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('club-'.$this->club->getKey().'-'.static::class))
                ->expireAfter($this->timeout)
                ->dontRelease(),
        ];
    }

    public function handle(CurrentClub $currentClub): void
    {
        Context::add('club_id', $this->club->getKey());

        $currentClub->run($this->club, fn () => $this->handleForClub());
    }

    abstract protected function handleForClub(): void;
}
```

Voeg `PLANNER_QUEUE_MODE=shared` toe aan `.env.example`.

- [ ] **Step 4:** tests → PASS.
- [ ] **Step 5: Commit** — `"Laat planner-jobs per club draaien op een configureerbare queue"`

---

### Task 11: Architectuurgrens, beheerderscommand, demo-data en spec bijwerken

**Files:**
- Create: `tests/Unit/ArchitectureTest.php`, `app/Console/Commands/CreatePlatformAdmin.php`, `database/seeders/DemoClubSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`, `docs/superpowers/specs/2026-10-07-standalone-planner-design.md` (§3.3 rollen, §8 fase 1/2)

**Interfaces:**
- Produces: `platform:create-admin {email} {--name=}` (maakt of promoveert user tot platformbeheerder, vraagt wachtwoord via `secret()` als user nieuw is). `DemoClubSeeder`: club "Demo VV" (`demo`, alle modules), user `demo@example.com` / `password` als clubbeheerder; alleen als `app()->isLocal()`.

- [ ] **Step 1: Architectuurtest**

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ArchitectureTest extends TestCase
{
    private const ALLOWED_PREFIXES = [
        'App\\Planner\\',
        'App\\Models\\Club',
        'App\\Models\\User',
        'App\\Support\\Tenancy\\',
    ];

    public function test_planner_code_only_depends_on_the_platform_contract(): void
    {
        $violations = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app/Planner'));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all('/^use\s+(App\\\\[^;\s]+)/m', file_get_contents($file->getPathname()), $matches);

            foreach ($matches[1] as $import) {
                if (! $this->isAllowed($import)) {
                    $violations[] = $file->getFilename().': '.$import;
                }
            }
        }

        $this->assertSame([], $violations);
    }

    private function isAllowed(string $import): bool
    {
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($import === rtrim($prefix, '\\') || str_starts_with($import, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
```

Run: `php85 artisan test --filter=ArchitectureTest` → PASS (en verifieer dat hij faalt door tijdelijk `use App\Notifications\ClubInvitation;` in `PlannerPlugin.php` te zetten; daarna weer weghalen).

- [ ] **Step 2: Command + seeder** — test eerst:

```php
public function test_create_platform_admin_promotes_existing_user(): void
{
    $user = \App\Models\User::factory()->create(['email' => 'stijn@example.nl']);

    $this->artisan('platform:create-admin', ['email' => 'stijn@example.nl'])->assertSuccessful();

    $this->assertTrue($user->fresh()->is_platform_admin);
}
```

(in `tests/Feature/Platform/CreatePlatformAdminTest.php` met `RefreshDatabase`). Implementatie: `User::firstWhere('email', $email)`; bestaat niet → `name` uit `--name` of `ask()`, wachtwoord via `secret()`; zet `is_platform_admin = true` via `forceFill()->save()`.

`DemoClubSeeder::run()`: `planner:sync-roles`; `Club::updateOrCreate(['slug' => 'demo'], ['name' => 'Demo VV', 'features' => array_column(PlannerFeature::cases(), 'value')])`; user `demo@example.com` (`password`), lid + `ClubAdmin`. `DatabaseSeeder` roept `planner:sync-roles` aan en, als `app()->isLocal()`, `DemoClubSeeder`.

- [ ] **Step 3: Spec bijwerken** — §3.3: vervang "Seeder maakt beide rollen bij het aanmaken van een club (via `ClubObserver`)" door "Rollen zijn globaal en worden aangemaakt/bijgewerkt met `planner:sync-roles`; toewijzing is per club." §8: verplaats `planner_settings` van fase 1 naar fase 2.

- [ ] **Step 4: Volledige verificatie**

```bash
php85 artisan migrate:fresh --seed
php85 artisan test
php85 vendor/bin/pint
php85 vendor/bin/phpstan analyse
```

Expected: alles groen. Handmatig: `https://volleybalplanner-clean.test/app` → login `demo@example.com`/`password` → dashboard van "Demo VV" met menu-item Gebruikers; `php85 artisan platform:create-admin stijn@trinos.nl` → `/admin` toont clubs.

- [ ] **Step 5: Commit** — `"Bewaak de planner-grens met een architectuurtest en voeg demo-data toe"`
