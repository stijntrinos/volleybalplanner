<?php

namespace App\Planner\Models;

use App\Planner\Enums\Source;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\VenueFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
