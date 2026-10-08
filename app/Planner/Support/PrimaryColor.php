<?php

namespace App\Planner\Support;

/**
 * The club colour as it may appear in a stylesheet: only a hex colour, so a
 * setting can never break out of the CSS declaration.
 */
final class PrimaryColor
{
    public const DEFAULT = DesignTokens::BRAND;

    public static function from(?string $color): string
    {
        $color = trim((string) $color);

        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color) === 1 ? $color : self::DEFAULT;
    }
}
