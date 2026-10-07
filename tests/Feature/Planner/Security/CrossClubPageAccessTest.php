<?php

namespace Tests\Feature\Planner\Security;

use App\Models\Club;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Pages\AwayMatches;
use App\Planner\Filament\Pages\DutiesDashboard;
use App\Planner\Filament\Pages\DutyPlanner;
use App\Planner\Filament\Pages\Exports;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Filament\Pages\NevoboChanges;
use App\Planner\Filament\Pages\VenueOccupancy;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Support\ClubRole;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Livewire page properties come back from the browser. A user of club A who
 * swaps in an id from club B must never see or change club B's data: the
 * BelongsToClub scope turns such ids into "not found".
 */
class CrossClubPageAccessTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private const SECRET = 'GEHEIM-CLUB-B';

    private ScheduleVersion $otherVersion;

    private FixtureMatch $otherMatch;

    protected function setUp(): void
    {
        parent::setUp();

        $other = $this->createClub();
        [$this->otherVersion, $this->otherMatch] = app(CurrentClub::class)->run($other, function (): array {
            $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true, 'name' => self::SECRET]);
            $venue = Venue::factory()->create(['name' => self::SECRET.' hal']);
            $court = Court::factory()->create(['venue_id' => $venue->id]);
            $match = FixtureMatch::factory()->create([
                'version_id' => $version->id, 'venue_id' => $venue->id, 'court_id' => $court->id,
                'match_code' => self::SECRET, 'home_team_name' => self::SECRET.' HS 1', 'away_team_name' => self::SECRET.' HS 2',
                'match_type' => MatchType::Home, 'date' => now()->addWeek()->toDateString(), 'time' => '10:00',
            ]);

            return [$version, $match];
        });

        $this->actingAsClubUser(ClubRole::ClubAdmin);
    }

    /** @return array<string, array{class-string}> */
    public static function versionPages(): array
    {
        return [
            'home matches' => [HomeMatches::class],
            'away matches' => [AwayMatches::class],
            'duty planner' => [DutyPlanner::class],
            'duties dashboard' => [DutiesDashboard::class],
            'nevobo changes' => [NevoboChanges::class],
            'venue occupancy' => [VenueOccupancy::class],
            'exports' => [Exports::class],
        ];
    }

    #[DataProvider('versionPages')]
    public function test_a_version_id_of_another_club_shows_nothing_of_that_club(string $page): void
    {
        try {
            Livewire::test($page)
                ->set('versionId', $this->otherVersion->id)
                ->assertDontSee(self::SECRET);
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        } catch (HttpException $exception) {
            $this->assertContains($exception->getStatusCode(), [403, 404]);
        }
    }

    public function test_the_match_modal_refuses_a_match_of_another_club(): void
    {
        try {
            Livewire::test(HomeMatches::class)
                ->call('openMatch', $this->otherMatch->id)
                ->assertDontSee(self::SECRET);
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        } catch (HttpException $exception) {
            $this->assertContains($exception->getStatusCode(), [403, 404]);
        }

        $this->assertSame(self::SECRET, app(CurrentClub::class)->run(
            Club::findOrFail($this->otherMatch->club_id),
            fn () => $this->otherMatch->fresh()->match_code,
        ));
    }
}
