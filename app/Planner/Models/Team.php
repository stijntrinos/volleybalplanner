<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Planner\Enums\Source;
use App\Planner\Support\TeamCode;
use App\Planner\Support\Weekday;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\TeamFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $club_id
 * @property string $code
 * @property string $name
 * @property Source $source
 * @property string|null $default_weekday
 * @property string|null $default_time
 * @property int|null $default_venue_id
 * @property int|null $default_court
 * @property int|null $default_match_duration
 * @property bool $plays_on_two_courts
 * @property bool $scores_own_match
 * @property Carbon|null $scores_own_match_until
 * @property bool $provides_referee
 * @property string|null $referee_license
 * @property bool $nevobo_referee
 * @property bool $has_duties
 * @property bool $include_in_duties
 * @property bool $volunteers
 * @property bool $include_volunteers
 * @property int $volunteer_points_used
 * @property int $volunteer_points_earned
 * @property int|null $volunteer_points_percentage
 * @property string|null $locker_room_category
 * @property bool $no_scoring_heavy_teams
 * @property bool $no_scoring_late
 * @property bool $no_hall_duty
 * @property bool $never_referees
 * @property bool $referees_restricted_teams
 * @property bool $referees_c_youth
 * @property bool $referees_youth_and_lower_women
 * @property int|null $weekly_max
 * @property string|null $playing_group
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club $club
 * @property-read Venue|null $defaultVenue
 * @property-read Collection<int, TeamSource> $sources
 * @property-read int|null $sources_count
 * @property-read Collection<int, Training> $trainings
 * @property-read int|null $trainings_count
 * @property-read Collection<int, TeamAbsence> $absences
 *
 * @method static \Database\Factories\Planner\TeamFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereDefaultCourt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereDefaultMatchDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereDefaultTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereDefaultVenueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereDefaultWeekday($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereHasDuties($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereIncludeInDuties($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereIncludeVolunteers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereLockerRoomCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereNevoboReferee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team wherePlaysOnTwoCourts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereProvidesReferee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereScoresOwnMatch($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereVolunteerPointsEarned($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereVolunteerPointsPercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereVolunteerPointsUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereVolunteers($value)
 *
 * @mixin \Eloquent
 */
class Team extends Model
{
    use BelongsToClub;

    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    protected $table = 'planner_teams';

    /** How default_weekday is written; converting goes through Weekday. */
    public const WEEKDAYS = Weekday::ABBREVIATIONS;

    /**
     * The columns the planner needs, for queries that select explicitly.
     *
     * @var list<string>
     */
    public const PLANNER_COLUMNS = [
        'id', 'club_id', 'code', 'name', 'source', 'default_weekday', 'default_time',
        'default_venue_id', 'default_court', 'default_match_duration',
        'plays_on_two_courts', 'scores_own_match', 'provides_referee', 'referee_license',
        'nevobo_referee', 'has_duties', 'include_in_duties', 'volunteers', 'include_volunteers',
        'volunteer_points_used', 'volunteer_points_earned', 'volunteer_points_percentage',
        'locker_room_category', ...self::PLANNING_RULES,
    ];

    /**
     * The per-team planning exceptions: which duties the duty planning
     * proposes the team.
     *
     * @var list<string>
     */
    public const PLANNING_RULES = [
        'scores_own_match_until', 'no_scoring_heavy_teams', 'no_scoring_late', 'no_hall_duty',
        'never_referees', 'referees_restricted_teams', 'referees_c_youth', 'referees_youth_and_lower_women',
        'weekly_max', 'playing_group',
    ];

    public const PLAYING_GROUP_WEEKDAYS = 'weekdays';

    public const PLAYING_GROUP_SATURDAY = 'saturday';

    /**
     * The Nevobo referee licences from low to high, with what they allow. A
     * team picks the licence a referee needs at minimum for its home matches.
     *
     * @var array<string, string>
     */
    public const REFEREE_LICENSES = [
        'V6' => 'Scheidsrechter t/m 3e klasse',
        'V4' => 'Scheidsrechter t/m 1e klasse',
        'R2.2' => 'Tweede scheidsrechter t/m 3e divisie',
        'R2' => 'Scheidsrechter t/m promotieklasse',
        'R1' => 'Scheidsrechter t/m 3e divisie',
        'N3' => 'Scheidsrechter t/m 2e divisie',
    ];

    protected $fillable = [
        'code', 'name', 'source', 'default_weekday', 'default_time',
        'default_venue_id', 'default_court', 'default_match_duration',
        'plays_on_two_courts', 'scores_own_match', 'provides_referee', 'referee_license', 'nevobo_referee', 'has_duties',
        'include_in_duties', 'volunteers', 'include_volunteers', 'volunteer_points_used', 'volunteer_points_earned',
        'volunteer_points_percentage', 'locker_room_category',
        'scores_own_match_until', 'no_scoring_heavy_teams', 'no_scoring_late', 'no_hall_duty',
        'never_referees', 'referees_restricted_teams', 'referees_c_youth', 'referees_youth_and_lower_women',
        'weekly_max', 'playing_group',
    ];

    protected function casts(): array
    {
        return [
            'source' => Source::class,
            'default_court' => 'integer',
            'default_match_duration' => 'integer',
            'plays_on_two_courts' => 'boolean',
            'scores_own_match' => 'boolean',
            'nevobo_referee' => 'boolean',
            'provides_referee' => 'boolean',
            'has_duties' => 'boolean',
            'include_in_duties' => 'boolean',
            'volunteers' => 'boolean',
            'include_volunteers' => 'boolean',
            'volunteer_points_used' => 'integer',
            'volunteer_points_earned' => 'integer',
            'volunteer_points_percentage' => 'integer',
            'scores_own_match_until' => 'date',
            'no_scoring_heavy_teams' => 'boolean',
            'no_scoring_late' => 'boolean',
            'no_hall_duty' => 'boolean',
            'never_referees' => 'boolean',
            'referees_restricted_teams' => 'boolean',
            'referees_c_youth' => 'boolean',
            'referees_youth_and_lower_women' => 'boolean',
            'weekly_max' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Team $team): void {
            foreach (self::defaultPlanningRules($team->code) as $field => $value) {
                // What was passed explicitly (the form, an import) wins.
                if (!array_key_exists($field, $team->getAttributes())) {
                    $team->setAttribute($field, $value);
                }
            }
        });
    }

    /**
     * The planning exceptions a new team starts with. A youth team gets no
     * hall duty, an A youth team referees C youth and B and C youth never
     * referee.
     *
     * @return array<string, bool>
     */
    public static function defaultPlanningRules(?string $code): array
    {
        $class = TeamCode::youthClass($code);

        return [
            'no_hall_duty' => $class !== null,
            'referees_c_youth' => $class === 'A',
            'never_referees' => in_array($class, ['B', 'C'], true),
        ];
    }

    /** @return array<string, string> */
    public static function playingGroups(): array
    {
        return [
            self::PLAYING_GROUP_WEEKDAYS => __('Doordeweeks'),
            self::PLAYING_GROUP_SATURDAY => __('Zaterdag'),
        ];
    }

    /**
     * Whether the team scores its own home match on this date. The flag can
     * have an end date; after it another team scores.
     */
    public function scoresOwnMatchOn(string $date): bool
    {
        return $this->scores_own_match
            && ($this->scores_own_match_until === null || $date <= $this->scores_own_match_until->toDateString());
    }

    protected static function newFactory(): TeamFactory
    {
        return TeamFactory::new();
    }

    /** The group this team is listed under, one of TeamCode::CATEGORY_ORDER. */
    public function category(): string
    {
        return TeamCode::category($this->code);
    }

    /** @return BelongsTo<Venue, $this> */
    public function defaultVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'default_venue_id');
    }

    /** @return HasMany<TeamSource, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(TeamSource::class);
    }

    /** @return HasMany<Training, $this> */
    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    /** @return HasMany<TeamAbsence, $this> */
    public function absences(): HasMany
    {
        return $this->hasMany(TeamAbsence::class)->orderBy('date_from');
    }
}
