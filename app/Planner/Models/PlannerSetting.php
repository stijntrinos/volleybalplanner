<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Planner\Enums\ChangeReason;
use App\Planner\Support\ClubTeamMatcher;
use App\Planner\Support\TeamCode;
use App\Planner\Support\Weekday;
use App\Support\Tenancy\BelongsToClub;
use App\Support\Tenancy\ReadOnlyClub;
use Database\Factories\Planner\PlannerSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The planner settings of a club: one row per club, created on demand by
 * current().
 *
 * @property int $id
 * @property int $club_id
 * @property int|null $main_venue_id
 * @property bool $requests_open
 * @property array<array-key, mixed>|null $request_reasons
 * @property array<array-key, mixed>|null $duty_request_reasons
 * @property Carbon|null $season_start
 * @property Carbon|null $season_end
 * @property Carbon|null $competition_start
 * @property Carbon|null $competition_end
 * @property string|null $youth_spring_start recurring MM-DD; set = youth matches stay in their half of the season
 * @property string|null $youth_spring_end recurring MM-DD, last day of the youth spring series
 * @property string|null $internal_senior_deadline recurring MM-DD; matches between two own senior teams before this day
 * @property Carbon|null $training_period_start
 * @property Carbon|null $training_period_end
 * @property int $buffer_minutes
 * @property int $cache_ttl_minutes
 * @property int $points_referee_duty
 * @property int $points_scorer_duty
 * @property int $points_hall_duty
 * @property int $points_volleystars
 * @property array<array-key, mixed>|null $hall_duty_weekdays
 * @property array<array-key, mixed>|null $hall_duty_exceptions
 * @property array<array-key, mixed>|null $team_group_order
 * @property string $schedule_footer
 * @property array<array-key, mixed>|null $duty_rules
 * @property bool $admin_override_external_error
 * @property array<array-key, mixed>|null $team_name_patterns
 * @property string|null $logo_path
 * @property string|null $primary_color
 * @property string|null $schedule_tagline
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club $club
 * @property-read Venue|null $mainVenue
 *
 * @method static \Database\Factories\Planner\PlannerSettingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereAdminOverrideExternalError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereBufferMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereCacheTtlMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereCompetitionEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereCompetitionStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereDutyRequestReasons($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereDutyRules($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereHallDutyExceptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereHallDutyWeekdays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereLogoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereMainVenueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting wherePointsHallDuty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting wherePointsRefereeDuty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting wherePointsScorerDuty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting wherePointsVolleystars($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting wherePrimaryColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereRequestReasons($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereRequestsOpen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereScheduleFooter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereScheduleTagline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereSeasonEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereSeasonStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereTeamGroupOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereTeamNamePatterns($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereTrainingPeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereTrainingPeriodStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlannerSetting whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class PlannerSetting extends Model
{
    use BelongsToClub;

    /** @use HasFactory<PlannerSettingFactory> */
    use HasFactory;

    protected $table = 'planner_settings';

    /** 0 is Sunday, 1 to 6 Monday to Saturday, as in the availability rules. */
    public const HALL_DUTY_WEEKDAYS_DEFAULT = [6, 0];

    protected $fillable = [
        'main_venue_id', 'season_start', 'season_end', 'competition_start', 'competition_end',
        'youth_spring_start', 'youth_spring_end', 'internal_senior_deadline', 'buffer_minutes',
        'cache_ttl_minutes', 'admin_override_external_error', 'training_period_start',
        'training_period_end', 'points_referee_duty', 'points_scorer_duty', 'points_hall_duty', 'points_volleystars',
        'schedule_footer', 'duty_rules', 'hall_duty_weekdays', 'hall_duty_exceptions', 'team_group_order',
        'requests_open', 'request_reasons', 'duty_request_reasons',
        'team_name_patterns', 'logo_path', 'primary_color', 'schedule_tagline',
    ];

    protected $attributes = [
        'team_name_patterns' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'hall_duty_weekdays' => 'array',
            'hall_duty_exceptions' => 'array',
            'team_group_order' => 'array',
            'team_name_patterns' => 'array',
            'season_start' => 'date',
            'season_end' => 'date',
            'competition_start' => 'date',
            'competition_end' => 'date',
            'training_period_start' => 'date',
            'training_period_end' => 'date',
            'buffer_minutes' => 'integer',
            'cache_ttl_minutes' => 'integer',
            'admin_override_external_error' => 'boolean',
            'points_referee_duty' => 'integer',
            'points_scorer_duty' => 'integer',
            'points_hall_duty' => 'integer',
            'points_volleystars' => 'integer',
            'duty_rules' => 'array',
            'request_reasons' => 'array',
            'duty_request_reasons' => 'array',
            'requests_open' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => ClubTeamMatcher::flush());
    }

    protected static function newFactory(): PlannerSettingFactory
    {
        return PlannerSettingFactory::new();
    }

    /**
     * The settings row of the current club, created on first use (but not
     * in read-only mode, which refuses the insert: the defaults are used).
     */
    public static function current(): self
    {
        return ReadOnlyClub::active() ? static::query()->firstOrNew() : static::query()->firstOrCreate();
    }

    /** @return BelongsTo<Venue, $this> */
    public function mainVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'main_venue_id');
    }

    /**
     * What contacts choose from when the club has not configured anything.
     *
     * @return list<string>
     */
    public static function defaultRequestReasons(): array
    {
        return [
            __('Te weinig spelers beschikbaar'),
            __('Vakantie of schoolactiviteit'),
            __('Geen scheidsrechter of teller beschikbaar'),
            __('Reisafstand of aanvangstijd'),
        ];
    }

    /**
     * Reasons that fit a referee, scorer or hall duty.
     *
     * @return list<string>
     */
    public static function defaultDutyRequestReasons(): array
    {
        return [
            __('Team is niet beschikbaar'),
            __('We willen de dienst ruilen'),
            __('Dienst staat verkeerd ingepland'),
            __('Onvoldoende vrijwilligers beschikbaar'),
        ];
    }

    /**
     * The reasons for a match change request. "other" is always last: without
     * that way out people pick a reason that is not true.
     *
     * @return list<string>
     */
    public function requestReasons(): array
    {
        return [...($this->ownReasons($this->request_reasons) ?: self::defaultRequestReasons()), ChangeReason::Other->value];
    }

    /**
     * Duty reasons are separate from match moves: a team may swap a duty
     * without the underlying match changing.
     *
     * @return list<string>
     */
    public function dutyRequestReasons(): array
    {
        return [...($this->ownReasons($this->duty_request_reasons) ?: self::defaultDutyRequestReasons()), ChangeReason::Other->value];
    }

    /**
     * Whether hall duty is planned on this weekday. Clubs that never
     * configured it fall back to the weekend.
     *
     * @param  0|1|2|3|4|5|6  $weekday
     */
    public function hasHallDutyOn(int $weekday): bool
    {
        $days = $this->hall_duty_weekdays ?? self::HALL_DUTY_WEEKDAYS_DEFAULT;

        return in_array($weekday, array_map('intval', $days), true);
    }

    /**
     * Whether there is hall duty on this date: a hall duty weekday that is not
     * listed as an exception (a tournament, a free weekend).
     */
    public function hasHallDutyOnDate(string $date): bool
    {
        return $this->hasHallDutyOn(Weekday::number($date))
            && !$this->isHallDutyException($date);
    }

    public function isHallDutyException(string $date): bool
    {
        return in_array($date, $this->hallDutyExceptions(), true);
    }

    /**
     * The exception dates as ISO dates. Input comes from an admin form, so
     * junk is dropped here instead of silently disabling a day later on.
     *
     * @return list<string>
     */
    public function hallDutyExceptions(): array
    {
        return array_values(array_unique(array_filter(array_map(
            static function (mixed $value): ?string {
                $date = trim((string) $value);

                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null;
            },
            $this->hall_duty_exceptions ?? [],
        ))));
    }

    /**
     * The order of the team groups (TeamCode::CATEGORY_ORDER keys). Groups the
     * club did not order come last in the default order, so a new category
     * never silently disappears.
     *
     * @return list<string>
     */
    public function teamGroupOrder(): array
    {
        $chosen = array_values(array_filter(
            array_map(fn (mixed $group): string => trim((string) $group), $this->team_group_order ?? []),
            fn (string $group): bool => in_array($group, TeamCode::CATEGORY_ORDER, true),
        ));

        return [...$chosen, ...array_values(array_diff(TeamCode::CATEGORY_ORDER, $chosen))];
    }

    /**
     * @param  array<int, mixed>|null  $reasons
     * @return list<string>
     */
    private function ownReasons(?array $reasons): array
    {
        return array_values(array_filter(
            array_map(fn (mixed $reason): string => trim((string) $reason), $reasons ?? []),
            fn (string $reason): bool => $reason !== ''
                && strcasecmp($reason, ChangeReason::Other->value) !== 0
                && strcasecmp($reason, ChangeReason::Other->getLabel()) !== 0,
        ));
    }
}
