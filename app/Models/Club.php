<?php

namespace App\Models;

use App\Planner\Features\PlannerFeature;
use Database\Factories\ClubFactory;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Club extends Model implements HasName
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
