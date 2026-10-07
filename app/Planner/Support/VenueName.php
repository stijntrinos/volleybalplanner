<?php

namespace App\Planner\Support;

use Illuminate\Support\Str;

/**
 * Venue names arrive in all kinds of spellings: "De Laar", "de laar",
 * "Sporthal De Laar", "Midachtensingel" versus "Middachtensingel". Comparing
 * goes through this key: accents removed, only letters and digits, and
 * repeated letters collapsed so a double consonant does not make another venue.
 *
 * Some sources put the address after the name: "Sporthal Middachtensingel,
 * Middachtensingel 4, 6825HN Arnhem". That is the same venue, so everything
 * from the first comma on does not count. A venue name itself has no comma;
 * what follows is the address. Without that cut the nightly sync read every
 * row of the planning file as a venue change and reset the court assignment
 * to the team's default court.
 */
final class VenueName
{
    public static function key(?string $name): string
    {
        $name = Str::before((string) $name, ',');
        $name = Str::lower(Str::ascii($name));
        $name = preg_replace('/[^a-z0-9]+/', '', $name) ?? '';

        return preg_replace('/([a-z])\1+/', '$1', $name) ?? $name;
    }

    public static function equals(?string $left, ?string $right): bool
    {
        return self::key($left) === self::key($right);
    }
}
