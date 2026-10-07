<?php

namespace Tests\Feature\Planner;

use App\Planner\Models\Activity;
use App\Planner\Models\ActivityColor;
use App\Planner\Models\Availability;
use App\Planner\Models\CalendarEvent;
use App\Planner\Models\ChangeRequest;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\NevoboChange;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use App\Planner\Models\Training;
use App\Planner\Models\UserPreference;
use App\Planner\Models\Venue;
use App\Planner\Models\VenueAlias;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Models\VolleystarsTask;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Every planner model is scoped to the current club: a record created in club
 * A is invisible in club B, including the related records its factory made.
 */
class TenancyIsolationTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    /** @var list<class-string<Model>> */
    private const MODELS = [
        Activity::class, ActivityColor::class, Availability::class, CalendarEvent::class, ChangeRequest::class,
        Court::class, FixtureMatch::class, HallDuty::class, NevoboChange::class, PlannerSetting::class,
        ScheduleVersion::class, Team::class, TeamSource::class, Training::class, UserPreference::class,
        Venue::class, VenueAlias::class, VolleystarsBlock::class, VolleystarsTask::class,
    ];

    /** Foreign keys to other planner records, which must live in the same club. */
    private const FOREIGN_KEYS = [
        'venue_id' => Venue::class,
        'court_id' => Court::class,
        'team_id' => Team::class,
        'version_id' => ScheduleVersion::class,
        'match_id' => FixtureMatch::class,
        'block_id' => VolleystarsBlock::class,
        'hall_duty_id' => HallDuty::class,
    ];

    public function test_every_planner_model_only_sees_its_own_club(): void
    {
        $current = app(CurrentClub::class);

        foreach (self::MODELS as $model) {
            $a = $this->createClub();
            $b = $this->createClub();

            $inA = $current->run($a, fn (): Model => $model::factory()->create());
            $inB = $current->run($b, fn (): Model => $model::factory()->create());

            $this->assertSame($a->id, $inA->club_id, $model);
            $this->assertSame($b->id, $inB->club_id, $model);
            $this->assertSame([$inA->getKey()], $current->run($a, fn (): array => $model::query()->pluck('id')->all()), $model);
            $this->assertSame([$inB->getKey()], $current->run($b, fn (): array => $model::query()->pluck('id')->all()), $model);

            foreach (self::FOREIGN_KEYS as $column => $related) {
                if ($inA->getAttribute($column) === null) {
                    continue;
                }

                $this->assertSame(
                    $a->id,
                    $related::withoutGlobalScope('club')->findOrFail($inA->getAttribute($column))->club_id,
                    "{$model}.{$column} points to another club",
                );
            }
        }
    }
}
