<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Planner\Enums\AwayChangeStatus;
use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\NevoboChangeStatus;
use App\Planner\Enums\NevoboStatus;
use App\Planner\Services\Duties\DutyPoints;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\FixtureMatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A match in a schedule version. Named FixtureMatch because "Match" is a
 * reserved word in PHP.
 *
 * @property int $id
 * @property int $club_id
 * @property int $version_id
 * @property Carbon|null $date
 * @property string|null $time
 * @property int $duration_minutes
 * @property string $home_team_name
 * @property string $away_team_name
 * @property int|null $home_team_id
 * @property int|null $away_team_id
 * @property string|null $pool
 * @property string $match_code
 * @property string|null $region
 * @property MatchType $match_type
 * @property int|null $venue_id
 * @property int|null $court_id
 * @property int|null $second_court_id
 * @property Carbon|null $provisional_date
 * @property string|null $provisional_time
 * @property int|null $provisional_duration_minutes
 * @property int|null $provisional_venue_id
 * @property int|null $provisional_court_id
 * @property int|null $provisional_second_court_id
 * @property string|null $referee
 * @property bool $referee_auto
 * @property bool $nevobo_referee
 * @property NevoboStatus $nevobo_status
 * @property NevoboChangeStatus $nevobo_change_status
 * @property Carbon|null $nevobo_changed_at
 * @property Carbon|null $nevobo_previous_date
 * @property string|null $nevobo_previous_time
 * @property string|null $nevobo_previous_venue
 * @property Carbon|null $nevobo_new_date
 * @property string|null $nevobo_new_time
 * @property string|null $nevobo_new_venue
 * @property string|null $scorer
 * @property int|null $referee_points the points of the referee duty, fixed when the duty came about
 * @property int|null $scorer_points
 * @property bool $scorer_auto
 * @property string|null $notes
 * @property Carbon|null $original_date
 * @property string|null $original_time
 * @property string|null $original_venue
 * @property string|null $original_court
 * @property ChangeStatus|null $change_status
 * @property bool $change_requested
 * @property Carbon|null $change_requested_at
 * @property int|null $change_requested_by
 * @property AwayChangeStatus $away_change_status
 * @property Carbon|null $away_new_date
 * @property string|null $away_new_time
 * @property string|null $away_new_venue
 * @property string|null $away_notes
 * @property bool $is_sitting_volleyball
 * @property int|null $suggested_court_id
 * @property int|null $suggested_duration_minutes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $awayTeam
 * @property-read Club $club
 * @property-read Court|null $court
 * @property-read Team|null $homeTeam
 * @property-read Court|null $provisionalCourt
 * @property-read Court|null $provisionalSecondCourt
 * @property-read Venue|null $provisionalVenue
 * @property-read Court|null $secondCourt
 * @property-read Court|null $suggestedCourt
 * @property-read Venue|null $venue
 * @property-read ScheduleVersion $version
 *
 * @method static \Database\Factories\Planner\FixtureMatchFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereAwayChangeStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereAwayNewDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereAwayNewTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereAwayNewVenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereAwayNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereAwayTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereAwayTeamName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereChangeRequested($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereChangeRequestedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereChangeRequestedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereChangeStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereCourtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereDurationMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereHomeTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereHomeTeamName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereIsSittingVolleyball($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereMatchCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereMatchType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereNevoboChangeStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereNevoboChangedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereNevoboNewDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereNevoboNewTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereNevoboNewVenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereNevoboPreviousDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereNevoboPreviousTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereNevoboPreviousVenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereNevoboReferee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereNevoboStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereOriginalCourt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereOriginalDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereOriginalTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereOriginalVenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch wherePool($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereProvisionalCourtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereProvisionalDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereProvisionalDurationMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereProvisionalSecondCourtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereProvisionalTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereProvisionalVenueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereReferee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereRefereeAuto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereRegion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereScorer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereScorerAuto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereSecondCourtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereSuggestedCourtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereSuggestedDurationMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereVenueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FixtureMatch whereVersionId($value)
 *
 * @mixin \Eloquent
 */
class FixtureMatch extends Model
{
    use BelongsToClub;

    /** @use HasFactory<FixtureMatchFactory> */
    use HasFactory;

    protected $table = 'planner_matches';

    /** Selectable match durations in minutes. */
    public const DURATION_OPTIONS = [60, 75, 90, 105, 120, 135, 150];

    protected $fillable = [
        'version_id', 'date', 'time', 'duration_minutes',
        'home_team_name', 'away_team_name', 'home_team_id', 'away_team_id',
        'pool', 'match_code', 'region', 'match_type',
        'venue_id', 'court_id', 'second_court_id',
        'provisional_date', 'provisional_time', 'provisional_duration_minutes',
        'provisional_venue_id', 'provisional_court_id', 'provisional_second_court_id',
        'referee', 'referee_auto', 'nevobo_referee', 'nevobo_status',
        'nevobo_change_status', 'nevobo_changed_at',
        'nevobo_previous_date', 'nevobo_previous_time', 'nevobo_previous_venue',
        'nevobo_new_date', 'nevobo_new_time', 'nevobo_new_venue',
        'scorer', 'scorer_auto', 'notes',
        'original_date', 'original_time', 'original_venue', 'original_court',
        'change_status', 'change_requested', 'change_requested_at', 'change_requested_by',
        'away_change_status', 'away_new_date', 'away_new_time', 'away_new_venue',
        'away_notes', 'is_sitting_volleyball', 'suggested_court_id', 'suggested_duration_minutes',
        'created_by', 'referee_points', 'scorer_points',
    ];

    protected function casts(): array
    {
        return [
            'match_type' => MatchType::class,
            'change_status' => ChangeStatus::class,
            'away_change_status' => AwayChangeStatus::class,
            'nevobo_status' => NevoboStatus::class,
            'nevobo_change_status' => NevoboChangeStatus::class,
            'date' => 'date',
            'duration_minutes' => 'integer',
            'provisional_date' => 'date',
            'provisional_duration_minutes' => 'integer',
            'scorer_auto' => 'boolean',
            'referee_auto' => 'boolean',
            'nevobo_referee' => 'boolean',
            'change_requested' => 'boolean',
            'is_sitting_volleyball' => 'boolean',
            'away_new_date' => 'date',
            'suggested_duration_minutes' => 'integer',
            'original_date' => 'date',
            'change_requested_at' => 'datetime',
            'nevobo_changed_at' => 'datetime',
            'nevobo_previous_date' => 'date',
            'nevobo_new_date' => 'date',
            'referee_points' => 'integer',
            'scorer_points' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (FixtureMatch $match) => DutyPoints::fillMatch($match));
    }

    protected static function newFactory(): FixtureMatchFactory
    {
        return FixtureMatchFactory::new();
    }

    /** Trims a stored time to HH:MM so '19:30' and '19:30:00' compare equal. */
    public static function shortTime(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    /**
     * Whether our planning has moved away from the Nevobo baseline held in the
     * original_* columns. Drives the red marking in the planner and tells the
     * RSS sync whether it may safely apply a new Nevobo schedule.
     */
    public function deviatesFromNevobo(): bool
    {
        return $this->original_date?->toDateString() !== $this->date?->toDateString()
            || self::shortTime($this->original_time) !== self::shortTime($this->time)
            || ($this->original_venue && $this->original_venue !== $this->venue?->name)
            || ($this->original_court && $this->original_court !== $this->court?->name);
    }

    /**
     * Whether a move to another day is waiting for approval. Until then the
     * match keeps its date, time and court.
     */
    public function hasProvisionalPlacement(): bool
    {
        return $this->provisional_date !== null;
    }

    /** @return BelongsTo<ScheduleVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ScheduleVersion::class, 'version_id');
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsTo<Court, $this> */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /** @return BelongsTo<Court, $this> */
    public function secondCourt(): BelongsTo
    {
        return $this->belongsTo(Court::class, 'second_court_id');
    }

    /** @return BelongsTo<Court, $this> */
    public function suggestedCourt(): BelongsTo
    {
        return $this->belongsTo(Court::class, 'suggested_court_id');
    }

    /** @return BelongsTo<Venue, $this> */
    public function provisionalVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'provisional_venue_id');
    }

    /** @return BelongsTo<Court, $this> */
    public function provisionalCourt(): BelongsTo
    {
        return $this->belongsTo(Court::class, 'provisional_court_id');
    }

    /** @return BelongsTo<Court, $this> */
    public function provisionalSecondCourt(): BelongsTo
    {
        return $this->belongsTo(Court::class, 'provisional_second_court_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }
}
