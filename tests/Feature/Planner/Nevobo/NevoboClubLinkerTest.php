<?php

namespace Tests\Feature\Planner\Nevobo;

use App\Models\Club;
use App\Planner\Enums\Source;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use App\Planner\Services\Nevobo\NevoboClubLinker;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesNevobo;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class NevoboClubLinkerTest extends TestCase
{
    use FakesNevobo, InteractsWithClubs, RefreshDatabase;

    private const HS1 = '/competitie/teams/cnh8q1u/heren/1';

    private const DS1 = '/competitie/teams/cnh8q1u/dames/1';

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeNevobo();
        $this->club = $this->useClub($this->createClub());
    }

    public function test_linking_creates_the_chosen_teams_with_their_feeds(): void
    {
        $result = app(NevoboClubLinker::class)->link($this->club, 'CNH8Q1U', [self::HS1, self::DS1]);

        $this->assertSame(['created' => 2, 'updated' => 0, 'sources' => 5], $result);
        $hs1 = Team::query()->where('code', 'HS 1')->sole();
        $this->assertSame('AETOS HS 1', $hs1->name);
        $this->assertSame(Source::Import, $hs1->source);
        $this->assertSame([
            'schedule' => 'https://api.nevobo.nl/export/team/CNH8Q1U/heren/1/programma.rss',
            'results' => 'https://api.nevobo.nl/export/team/CNH8Q1U/heren/1/resultaten.rss',
            'standings' => 'https://api.nevobo.nl/export/poule/regio-oost/competitie-seniorencompetitie-2/regio-oost-3ch-2/stand.rss',
        ], $hs1->sources()->orderBy('id')->get()->mapWithKeys(fn (TeamSource $source): array => [$source->type->value => $source->url])->all());
        $this->assertSame(1, TeamSource::query()->forImport()->where('team_id', $hs1->id)->count());
        $this->assertSame('AETOS Plauti DS 1', Team::query()->where('code', 'DS 1')->value('name'));
    }

    public function test_relinking_updates_feeds_without_duplicates_and_keeps_manual_team_fields(): void
    {
        $existing = Team::factory()->create(['code' => 'HS1', 'name' => 'Heren 1', 'default_time' => '20:00', 'source' => Source::Manual]);
        $poule = TeamSource::factory()->create(['team_id' => $existing->id, 'name' => 'Competitie', 'url' => 'https://api.nevobo.nl/export/poule/regio-oost/3CH/programma.rss']);
        $stale = TeamSource::factory()->create(['team_id' => $existing->id, 'url' => 'https://api.nevobo.nl/export/team/CNH8Q1U/heren/9/programma.rss']);
        $linker = app(NevoboClubLinker::class);

        $first = $linker->link($this->club, 'CNH8Q1U', [self::HS1]);
        $second = $linker->link($this->club, 'CNH8Q1U', [self::HS1]);

        $this->assertSame(['created' => 0, 'updated' => 1, 'sources' => 3], $first);
        $this->assertSame(['created' => 0, 'updated' => 1, 'sources' => 0], $second);
        $existing->refresh();
        $this->assertSame(['HS1', 'Heren 1', '20:00', Source::Manual], [$existing->code, $existing->name, $existing->default_time, $existing->source]);
        $this->assertSame(1, Team::query()->count());
        $this->assertSame(4, $existing->sources()->count());
        $this->assertSame('https://api.nevobo.nl/export/team/CNH8Q1U/heren/1/programma.rss', $stale->refresh()->url);
        $this->assertSame('https://api.nevobo.nl/export/poule/regio-oost/3CH/programma.rss', $poule->refresh()->url);
    }

    public function test_patterns_and_club_code_are_stored_keeping_existing_patterns(): void
    {
        PlannerSetting::current()->update(['team_name_patterns' => ['AET', 'aetos']]);

        app(NevoboClubLinker::class)->link($this->club, 'cnh8q1u', [self::HS1]);
        $settings = PlannerSetting::current();

        $this->assertSame(['AET', 'aetos'], $settings->team_name_patterns);
        $this->assertSame('CNH8Q1U', $settings->nevobo_club_code);
    }

    public function test_the_nevobo_club_name_becomes_a_pattern_when_missing(): void
    {
        app(NevoboClubLinker::class)->link($this->club, 'CNH8Q1U', [self::HS1]);

        $this->assertSame(['AETOS'], PlannerSetting::current()->team_name_patterns);
    }

    public function test_only_the_given_club_is_changed_and_unknown_team_ids_are_ignored(): void
    {
        $other = $this->createClub();
        $current = $this->createClub();
        $this->useClub($current);

        $result = app(NevoboClubLinker::class)->link($other, 'CNH8Q1U', [self::HS1, '/competitie/teams/ckl5c67/heren/1']);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, Team::query()->count());
        $this->assertSame(0, Team::query()->withoutGlobalScopes()->where('club_id', $this->club->id)->count());
        $this->assertSame(['HS 1'], app(CurrentClub::class)->run($other, fn (): array => Team::query()->pluck('code')->all()));
        $this->assertSame('CNH8Q1U', app(CurrentClub::class)->run($other, fn (): ?string => PlannerSetting::current()->nevobo_club_code));
    }
}
