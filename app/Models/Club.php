<?php

namespace App\Models;

use App\Planner\Features\PlannerFeature;
use Database\Factories\ClubFactory;
use Filament\Models\Contracts\HasCurrentTenantLabel;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 * @property array<array-key, mixed>|null $features
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\ClubFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereFeatures($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Club extends Model implements HasCurrentTenantLabel, HasName
{
    /** @use HasFactory<ClubFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'is_active', 'features'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'features' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    /**
     * Shown under the club name in the sidebar switcher. A volleyball season
     * runs from August to July, like the Nevobo competition.
     */
    public function getCurrentTenantLabel(): string
    {
        $start = now()->month >= 8 ? now()->year : now()->year - 1;

        return __('Seizoen :season', ['season' => $start.'-'.($start + 1)]);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * A feature only counts as enabled when everything it requires is enabled
     * too, so a stray value in the database can't switch on half a module.
     */
    public function hasFeature(PlannerFeature $feature): bool
    {
        if (!in_array($feature->value, $this->features ?? [], true)) {
            return false;
        }

        foreach ($feature->requires() as $required) {
            if (!$this->hasFeature($required)) {
                return false;
            }
        }

        return true;
    }
}
