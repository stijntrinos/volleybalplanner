<?php

namespace App\Models;

use App\Planner\Features\PlanLimit;
use App\Planner\Features\PlannerFeature;
use App\Planner\Support\CommitteeRole;
use Database\Factories\ClubFactory;
use Filament\Models\Contracts\HasCurrentTenantLabel;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 * @property int|null $plan_id
 * @property list<string>|null $committee_groups
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 * @property-read Plan|null $plan
 *
 * @method static \Database\Factories\ClubFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Club whereCreatedAt($value)
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

    protected $fillable = ['name', 'slug', 'is_active', 'plan_id', 'committee_groups'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'committee_groups' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Every club has its own planning committee role.
        static::created(fn (Club $club): Role => CommitteeRole::sync($club));
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

    /** @return HasMany<Invitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /** Members plus open invitations: what counts against the admin limit. */
    public function seatsTaken(): int
    {
        return $this->users()->count() + $this->invitations()->pending()->count();
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Modules come from the subscription. A feature only counts as enabled
     * when everything it requires is in the plan too, so a half-configured
     * plan can't switch on half a module.
     */
    public function hasFeature(PlannerFeature $feature): bool
    {
        if (!$this->plan?->includes($feature)) {
            return false;
        }

        foreach ($feature->requires() as $required) {
            if (!$this->hasFeature($required)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> values of the modules the club has */
    public function featureValues(): array
    {
        return array_values(array_map(
            fn (PlannerFeature $feature): string => $feature->value,
            array_filter(PlannerFeature::cases(), fn (PlannerFeature $feature): bool => $this->hasFeature($feature)),
        ));
    }

    /** How many the plan allows; null is unlimited (also for a club without a plan). */
    public function planLimit(PlanLimit $limit): ?int
    {
        return $this->plan?->limit($limit);
    }

    /** Whether the club may add one more, given how many it has now. */
    public function allowsAnother(PlanLimit $limit, int $current): bool
    {
        $max = $this->planLimit($limit);

        return $max === null || $current < $max;
    }

    public function planName(): ?string
    {
        return $this->plan?->name;
    }

    /**
     * Name of the plan to upgrade to for a module or for one more of
     * something; null when no plan offers it.
     */
    public function upgradePlanName(PlannerFeature|PlanLimit $need, int $needed = 0): ?string
    {
        $plan = $this->plan
            ? $this->plan->nextPlanWith($need, $needed)
            : Plan::firstWith($need, $needed);

        return $plan?->name;
    }
}
