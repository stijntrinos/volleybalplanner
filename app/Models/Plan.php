<?php

namespace App\Models;

use App\Planner\Features\PlanLimit;
use App\Planner\Features\PlannerFeature;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A subscription: which optional planner modules a club gets and how many
 * teams, sporthallen and beheerders it may have. Empty limits are unlimited.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $tagline
 * @property int|null $price
 * @property int|null $max_teams
 * @property int|null $max_venues
 * @property int|null $max_admins
 * @property list<string>|null $features
 * @property list<string>|null $highlights
 * @property bool $is_featured
 * @property bool $is_public
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'tagline', 'price', 'max_teams', 'max_venues', 'max_admins',
        'features', 'highlights', 'is_featured', 'is_public', 'sort_order',
    ];

    protected $attributes = ['is_featured' => false, 'is_public' => true, 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'max_teams' => 'integer',
            'max_venues' => 'integer',
            'max_admins' => 'integer',
            'features' => 'array',
            'highlights' => 'array',
            'is_featured' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<Club, $this> */
    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /** @param  Builder<self>  $query */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }

    public function includes(PlannerFeature $feature): bool
    {
        return in_array($feature->value, $this->features ?? [], true);
    }

    /** Null means unlimited. */
    public function limit(PlanLimit $limit): ?int
    {
        return $this->getAttribute($limit->column());
    }

    /** @return list<PlannerFeature> */
    public function plannerFeatures(): array
    {
        return array_values(array_filter(array_map(
            fn (string $value): ?PlannerFeature => PlannerFeature::tryFrom($value),
            $this->features ?? [],
        )));
    }

    public function formattedPrice(): ?string
    {
        return $this->price === null ? null : '€'.number_format($this->price, 0, ',', '.');
    }

    /**
     * The first plan after this one (in pricing order) that offers what a
     * club is missing: a module, or room for more of something.
     */
    public function nextPlanWith(PlannerFeature|PlanLimit $need, int $needed = 0): ?self
    {
        return $this->plansAfter()->first(fn (self $plan): bool => self::offers($plan, $need, $needed));
    }

    /** The plan after this one in pricing order. */
    public function nextPlan(): ?self
    {
        return $this->plansAfter()->first();
    }

    /** The first plan (in pricing order) that offers it; for clubs without a plan. */
    public static function firstWith(PlannerFeature|PlanLimit $need, int $needed = 0): ?self
    {
        return self::query()->ordered()->get()->first(fn (self $plan): bool => self::offers($plan, $need, $needed));
    }

    /** @return Collection<int, self> */
    private function plansAfter(): Collection
    {
        return self::query()->ordered()
            ->where(fn (Builder $query) => $query->where('sort_order', '>', $this->sort_order)
                ->orWhere(fn (Builder $query) => $query->where('sort_order', $this->sort_order)->where('id', '>', $this->id)))
            ->get();
    }

    private static function offers(self $plan, PlannerFeature|PlanLimit $need, int $needed): bool
    {
        if ($need instanceof PlannerFeature) {
            return $plan->includes($need) && array_all($need->requires(), fn (PlannerFeature $required): bool => $plan->includes($required));
        }

        $limit = $plan->limit($need);

        return $limit === null || $limit >= $needed;
    }
}
