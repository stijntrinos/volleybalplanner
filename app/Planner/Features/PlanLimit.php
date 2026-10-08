<?php

namespace App\Planner\Features;

use Filament\Support\Contracts\HasLabel;

/**
 * What a subscription caps besides the modules. A club stays over a limit
 * when it already was (nothing is removed); it just can't add more.
 */
enum PlanLimit: string implements HasLabel
{
    case Teams = 'teams';
    case Venues = 'venues';
    case Admins = 'admins';

    public function getLabel(): string
    {
        return match ($this) {
            self::Teams => __('Teams'),
            self::Venues => __('Sporthallen'),
            self::Admins => __('Beheerders'),
        };
    }

    /** The column on the plans table, null meaning unlimited. */
    public function column(): string
    {
        return 'max_'.$this->value;
    }

    /** "Je pakket heeft ruimte voor 15 teams." */
    public function reachedMessage(int $max): string
    {
        return match ($this) {
            self::Teams => trans_choice('Je pakket heeft ruimte voor :count team.|Je pakket heeft ruimte voor :count teams.', $max),
            self::Venues => trans_choice('Je pakket heeft ruimte voor :count sporthal.|Je pakket heeft ruimte voor :count sporthallen.', $max),
            self::Admins => trans_choice('Je pakket heeft ruimte voor :count beheerder.|Je pakket heeft ruimte voor :count beheerders.', $max),
        };
    }
}
