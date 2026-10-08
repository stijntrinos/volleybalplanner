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
 * @property bool $require_strong_auth
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $subscription_starts_at
 * @property Carbon|null $subscription_ends_at
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

    protected $fillable = ['name', 'slug', 'is_active', 'plan_id', 'committee_groups', 'require_strong_auth', 'trial_ends_at', 'subscription_starts_at', 'subscription_ends_at'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'committee_groups' => 'array',
            'require_strong_auth' => 'boolean',
            'trial_ends_at' => 'date',
            'subscription_starts_at' => 'date',
            'subscription_ends_at' => 'date',
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

    /**
     * The day the trial or subscription ran out, or null while it runs (or
     * when no period is set). From the day after, the club is read-only.
     */
    public function subscriptionEndedOn(): ?Carbon
    {
        $today = now()->startOfDay();

        if ($this->trial_ends_at !== null && $this->subscription_starts_at === null && $this->trial_ends_at->lt($today)) {
            return $this->trial_ends_at;
        }

        if ($this->subscription_ends_at !== null && $this->subscription_ends_at->lt($today)) {
            return $this->subscription_ends_at;
        }

        return null;
    }

    /**
     * Read-only mode: after the trial or subscription ended, everyone can
     * still look at the planning, but nothing can be changed until the
     * period is extended in /admin.
     */
    public function isReadOnly(): bool
    {
        return $this->subscriptionEndedOn() !== null;
    }

    /**
     * A notice about the subscription period, or null when there is nothing
     * to say: a trial or subscription that ends within the warning window,
     * or one that has ended.
     *
     * @return array{tone: 'warning'|'danger', message: string}|null
     */
    public function subscriptionNotice(): ?array
    {
        $today = now()->startOfDay();
        $date = fn (Carbon $day): string => $day->translatedFormat('j F Y');

        if ($this->trial_ends_at !== null && $this->subscription_starts_at === null) {
            if ($this->trial_ends_at->lt($today)) {
                return ['tone' => 'danger', 'message' => __('De proefperiode is afgelopen op :date.', ['date' => $date($this->trial_ends_at)])];
            }
            if ($this->trial_ends_at->lte($today->copy()->addDays(14))) {
                return ['tone' => 'warning', 'message' => __('De proefperiode loopt tot :date.', ['date' => $date($this->trial_ends_at)])];
            }
        }

        if ($this->subscription_ends_at !== null) {
            if ($this->subscription_ends_at->lt($today)) {
                return ['tone' => 'danger', 'message' => __('Het abonnement is afgelopen op :date.', ['date' => $date($this->subscription_ends_at)])];
            }
            if ($this->subscription_ends_at->lte($today->copy()->addDays(30))) {
                return ['tone' => 'warning', 'message' => __('Het abonnement loopt af op :date.', ['date' => $date($this->subscription_ends_at)])];
            }
        }

        return null;
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
