<?php

namespace App\Planner\Models;

use App\Planner\Enums\Source;
use App\Planner\Support\TeamCode;
use App\Planner\Support\Weekday;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'plays_on_two_courts', 'scores_own_match', 'provides_referee',
        'nevobo_referee', 'has_duties', 'include_in_duties', 'volunteers', 'include_volunteers',
        'volunteer_points_used', 'volunteer_points_earned', 'volunteer_points_percentage',
        'locker_room_category',
    ];

    protected $fillable = [
        'code', 'name', 'source', 'default_weekday', 'default_time',
        'default_venue_id', 'default_court', 'default_match_duration',
        'plays_on_two_courts', 'scores_own_match', 'provides_referee', 'nevobo_referee', 'has_duties',
        'include_in_duties', 'volunteers', 'include_volunteers', 'volunteer_points_used', 'volunteer_points_earned',
        'volunteer_points_percentage', 'locker_room_category',
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
        ];
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
}
