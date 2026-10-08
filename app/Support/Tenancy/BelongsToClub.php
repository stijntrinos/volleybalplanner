<?php

namespace App\Support\Tenancy;

use App\Models\Club;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the current club and fills club_id on create. Querying
 * without an active club throws instead of silently returning every club.
 * Saving or deleting while the club is read-only throws ClubIsReadOnly,
 * unless the model implements WritableWhenReadOnly.
 */
trait BelongsToClub
{
    protected static function bootBelongsToClub(): void
    {
        static::addGlobalScope('club', function (Builder $query): void {
            $query->where($query->qualifyColumn('club_id'), app(CurrentClub::class)->id());
        });

        static::creating(function (Model $model): void {
            if (!$model->getAttribute('club_id')) {
                $model->setAttribute('club_id', app(CurrentClub::class)->id());
            }
        });

        $guard = function (Model $model): void {
            if (!$model instanceof WritableWhenReadOnly) {
                ReadOnlyClub::guard();
            }
        };
        static::saving($guard);
        static::deleting($guard);
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
