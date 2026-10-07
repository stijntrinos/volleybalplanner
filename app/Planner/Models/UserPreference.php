<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Models\User;
use App\Planner\Enums\OverlapPolicy;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\UserPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Deliberately without a policy: every lookup goes through
 * user_id = auth()->id(), so there is nothing to grant to anyone else.
 *
 * @property int $id
 * @property int $club_id
 * @property int $user_id
 * @property int|null $default_venue_id
 * @property int|null $active_version_id
 * @property Carbon|null $selected_date
 * @property array<array-key, mixed>|null $hidden_venues
 * @property string $nevobo_grouping
 * @property bool $show_trainings
 * @property bool $duties_ask_reason
 * @property bool $duties_validation_mode
 * @property OverlapPolicy $overlap_policy
 * @property bool $publish_with_conflicts
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ScheduleVersion|null $activeVersion
 * @property-read Club $club
 * @property-read Venue|null $defaultVenue
 * @property-read User $user
 *
 * @method static \Database\Factories\Planner\UserPreferenceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereActiveVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereDefaultVenueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereDutiesAskReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereDutiesValidationMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereHiddenVenues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereNevoboGrouping($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereOverlapPolicy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference wherePublishWithConflicts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereSelectedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereShowTrainings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserPreference whereUserId($value)
 *
 * @mixin \Eloquent
 */
class UserPreference extends Model
{
    use BelongsToClub;

    /** @use HasFactory<UserPreferenceFactory> */
    use HasFactory;

    protected $table = 'planner_user_preferences';

    /**
     * The database defaults, so a preference created with firstOrCreate()
     * has them in memory too instead of null until it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'nevobo_grouping' => 'team',
        'show_trainings' => true,
        'duties_ask_reason' => true,
        'duties_validation_mode' => true,
        'overlap_policy' => 'warn',
        'publish_with_conflicts' => false,
    ];

    protected $fillable = [
        'user_id', 'default_venue_id', 'active_version_id', 'selected_date',
        'hidden_venues', 'nevobo_grouping', 'overlap_policy', 'publish_with_conflicts',
        'show_trainings', 'duties_ask_reason', 'duties_validation_mode',
    ];

    protected function casts(): array
    {
        return [
            'overlap_policy' => OverlapPolicy::class,
            'selected_date' => 'date',
            'hidden_venues' => 'array',
            'publish_with_conflicts' => 'boolean',
            'show_trainings' => 'boolean',
            'duties_ask_reason' => 'boolean',
            'duties_validation_mode' => 'boolean',
        ];
    }

    protected static function newFactory(): UserPreferenceFactory
    {
        return UserPreferenceFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function defaultVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'default_venue_id');
    }

    /** @return BelongsTo<ScheduleVersion, $this> */
    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(ScheduleVersion::class, 'active_version_id');
    }
}
