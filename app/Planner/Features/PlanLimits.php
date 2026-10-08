<?php

namespace App\Planner\Features;

use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Support\Tenancy\CurrentClub;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * The plan limits of the current club. A club over its limit keeps all its
 * data; it just can't add more until it upgrades.
 */
class PlanLimits
{
    public static function count(PlanLimit $limit): int
    {
        return match ($limit) {
            PlanLimit::Teams => Team::query()->count(),
            // Sporthallen the club plans in; away venues don't count.
            PlanLimit::Venues => Venue::query()->where('is_managed', true)->where('is_active', true)->count(),
            // Open invitations count too, or a club could invite past the limit.
            PlanLimit::Admins => (int) app(CurrentClub::class)->get()?->seatsTaken(),
        };
    }

    public static function reached(PlanLimit $limit): bool
    {
        $club = app(CurrentClub::class)->get();

        return $club !== null && !$club->allowsAnother($limit, self::count($limit));
    }

    /**
     * Stands in for the create button once the limit is reached: same label,
     * a lock, and the upgrade prompt instead of the form.
     */
    public static function lockedAction(PlanLimit $limit, string $name, string $label): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('gray')
            ->tooltip(fn (): ?string => ($max = app(CurrentClub::class)->get()?->planLimit($limit)) !== null ? $limit->reachedMessage($max) : null)
            ->alpineClickHandler("\$dispatch('planner-upgrade', { limit: '{$limit->value}' })")
            ->visible(fn (): bool => self::reached($limit));
    }
}
