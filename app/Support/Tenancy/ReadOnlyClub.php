<?php

namespace App\Support\Tenancy;

use App\Models\Club;

/**
 * Read-only mode for a club whose trial or subscription ended
 * (Club::isReadOnly()). Three layers keep it closed:
 *
 * - every write ability ("Update:FixtureMatch", "update", ...) is denied,
 *   so Filament hides create/edit/delete buttons and the planner pages
 *   refuse their write actions;
 * - BelongsToClub refuses to save or delete a club model, as a safety net
 *   for any write path without an ability check;
 * - jobs and per-club commands skip the club, so the nightly Nevobo sync
 *   leaves the frozen planning alone.
 */
final class ReadOnlyClub
{
    /** Ability verbs that change data (Shield's policy methods, lowercased). */
    private const WRITE_VERBS = [
        'create', 'update', 'delete', 'deleteany', 'restore', 'restoreany',
        'forcedelete', 'forcedeleteany', 'replicate', 'reorder',
    ];

    public static function club(): ?Club
    {
        $club = app(CurrentClub::class)->get();

        return $club?->isReadOnly() ? $club : null;
    }

    public static function active(): bool
    {
        return self::club() !== null;
    }

    /** Whether the ability ("Update:Team" or a policy method like "update") writes. */
    public static function isWriteAbility(string $ability): bool
    {
        $verb = str_contains($ability, ':') ? strstr($ability, ':', true) : $ability;

        return in_array(strtolower($verb), self::WRITE_VERBS, true);
    }

    /** Whether the ability must be denied because the current club is read-only. */
    public static function blocks(string $ability): bool
    {
        return self::isWriteAbility($ability) && self::active();
    }

    /** @throws ClubIsReadOnly */
    public static function guard(): void
    {
        if (self::active()) {
            throw new ClubIsReadOnly;
        }
    }
}
