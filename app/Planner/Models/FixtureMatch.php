<?php

namespace App\Planner\Models;

use App\Planner\Enums\AwayChangeStatus;
use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\NevoboChangeStatus;
use App\Planner\Enums\NevoboStatus;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\FixtureMatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A match in a schedule version. Named FixtureMatch because "Match" is a
 * reserved word in PHP.
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
        'created_by',
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
        ];
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
