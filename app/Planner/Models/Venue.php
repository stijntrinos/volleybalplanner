<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Planner\Enums\Source;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\VenueFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $club_id
 * @property string|null $venue_code
 * @property string $name
 * @property string|null $city
 * @property string|null $address
 * @property bool $is_managed
 * @property bool $has_duties
 * @property bool $show_locker_rooms_on_schedule
 * @property bool $for_matches
 * @property bool $for_trainings
 * @property Source $source
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, VenueAlias> $aliases
 * @property-read int|null $aliases_count
 * @property-read Club $club
 * @property-read Collection<int, Court> $courts
 * @property-read int|null $courts_count
 * @property-read Collection<int, Training> $trainings
 * @property-read int|null $trainings_count
 *
 * @method static \Database\Factories\Planner\VenueFactory factory($count = null, $state = [])
 * @method static Builder<static>|Venue inPlanningOrder(?int $clubId = null)
 * @method static Builder<static>|Venue newModelQuery()
 * @method static Builder<static>|Venue newQuery()
 * @method static Builder<static>|Venue query()
 * @method static Builder<static>|Venue whereAddress($value)
 * @method static Builder<static>|Venue whereCity($value)
 * @method static Builder<static>|Venue whereClubId($value)
 * @method static Builder<static>|Venue whereCreatedAt($value)
 * @method static Builder<static>|Venue whereForMatches($value)
 * @method static Builder<static>|Venue whereForTrainings($value)
 * @method static Builder<static>|Venue whereHasDuties($value)
 * @method static Builder<static>|Venue whereId($value)
 * @method static Builder<static>|Venue whereIsActive($value)
 * @method static Builder<static>|Venue whereIsManaged($value)
 * @method static Builder<static>|Venue whereName($value)
 * @method static Builder<static>|Venue whereShowLockerRoomsOnSchedule($value)
 * @method static Builder<static>|Venue whereSource($value)
 * @method static Builder<static>|Venue whereUpdatedAt($value)
 * @method static Builder<static>|Venue whereVenueCode($value)
 *
 * @mixin \Eloquent
 */
class Venue extends Model
{
    use BelongsToClub;

    /** @use HasFactory<VenueFactory> */
    use HasFactory;

    protected $table = 'planner_venues';

    protected $fillable = [
        'venue_code', 'name', 'city', 'address', 'is_managed', 'has_duties', 'show_locker_rooms_on_schedule',
        'for_matches', 'for_trainings', 'source', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_managed' => 'boolean',
            'has_duties' => 'boolean',
            'show_locker_rooms_on_schedule' => 'boolean',
            'for_matches' => 'boolean',
            'for_trainings' => 'boolean',
            'is_active' => 'boolean',
            'source' => Source::class,
        ];
    }

    protected static function newFactory(): VenueFactory
    {
        return VenueFactory::new();
    }

    /**
     * The main venue comes first everywhere: most home matches are played
     * there, so that is where the planning committee looks first. The rest
     * follows by name. Pass a club id to read another club's main venue.
     *
     * @param  Builder<Venue>  $query
     */
    #[Scope]
    protected function inPlanningOrder(Builder $query, ?int $clubId = null): void
    {
        $main = PlannerSetting::query()
            ->when($clubId, fn (Builder $settings): Builder => $settings->withoutGlobalScopes()->where('club_id', $clubId))
            ->value('main_venue_id');

        $query
            ->when($main, fn (Builder $venues): Builder => $venues->orderByRaw(
                'CASE WHEN '.$query->qualifyColumn('id').' = ? THEN 0 ELSE 1 END', [$main],
            ))
            ->orderBy('name');
    }

    /** @return HasMany<Court, $this> */
    public function courts(): HasMany
    {
        return $this->hasMany(Court::class)->orderBy('sort_order')->orderBy('name');
    }

    /** @return HasMany<VenueAlias, $this> */
    public function aliases(): HasMany
    {
        return $this->hasMany(VenueAlias::class)->orderBy('name');
    }

    /** @return HasMany<Training, $this> */
    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }
}
