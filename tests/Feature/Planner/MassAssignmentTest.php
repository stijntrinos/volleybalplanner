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
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every planner model has an explicit $fillable that matches its table: a
 * forgotten column would silently disappear on save. club_id is deliberately
 * not fillable; BelongsToClub sets it.
 */
class MassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array{class-string<Model>}> */
    public static function models(): array
    {
        return [
            [Activity::class], [ActivityColor::class], [Availability::class], [CalendarEvent::class],
            [ChangeRequest::class], [Court::class], [FixtureMatch::class], [HallDuty::class],
            [NevoboChange::class], [PlannerSetting::class], [ScheduleVersion::class], [Team::class],
            [TeamSource::class], [Training::class], [UserPreference::class], [Venue::class],
            [VenueAlias::class], [VolleystarsBlock::class], [VolleystarsTask::class],
        ];
    }

    /** @param  class-string<Model>  $class */
    #[DataProvider('models')]
    public function test_every_column_is_fillable_and_every_fillable_column_exists(string $class): void
    {
        $model = new $class;
        $fillable = $model->getFillable();

        $this->assertNotSame([], $fillable, $class.' should have an explicit $fillable');

        $columns = Schema::getColumnListing($model->getTable());
        $this->assertSame([], array_values(array_diff($fillable, $columns)), $class.' lists columns that do not exist');

        $missing = array_diff($columns, [...$fillable, 'id', 'club_id', 'created_at', 'updated_at', 'deleted_at']);
        $this->assertSame([], array_values($missing), $class.' is missing columns in $fillable');
        $this->assertNotContains('club_id', $fillable, $class.' must not make club_id fillable');
    }

    public function test_an_unknown_attribute_fails_instead_of_silently_disappearing(): void
    {
        $this->expectException(MassAssignmentException::class);

        new PlannerSetting(['does_not_exist' => 1]);
    }
}
