<?php

namespace App\Planner\Support;

/**
 * The largest font size at which a line still fits on one line.
 *
 * Team names vary a lot: "VV Demo DS 4 - Gemini DS 3" gets the full size,
 * "VV Demo JA 1 - Bielderman Koetsier/SSS JA 1" does not. Without this
 * calculation such a line wraps to a second line and the whole row of the
 * schedule grows, including the cards next to it.
 *
 * The width comes from the font file itself. Chrome places the glyphs a
 * fraction differently than imagettfbbox calculates, so a small margin is
 * kept.
 */
final class TextMeasure
{
    private const MARGIN = 0.97;

    /** @var array<string, int> */
    private static array $remembered = [];

    public static function fitting(string $text, int $maxWidth, int $start, int $minimum = 9): int
    {
        $text = trim($text);
        if ($text === '' || $maxWidth <= 0) {
            return $start;
        }

        $key = $text.'|'.$maxWidth.'|'.$start.'|'.$minimum;
        if (isset(self::$remembered[$key])) {
            return self::$remembered[$key];
        }

        $space = (int) round($maxWidth * self::MARGIN);
        $size = $start;
        while ($size > $minimum && self::width($text, $size) > $space) {
            $size--;
        }

        return self::$remembered[$key] = $size;
    }

    /** The bold font the schedule is set in; also used by RendersWithChrome. */
    public static function fontPath(string $file = 'InriaSans-Bold.ttf'): string
    {
        return resource_path('fonts/planner/'.$file);
    }

    private static function width(string $text, int $size): float
    {
        $font = self::fontPath();
        if (!is_file($font)) {
            // Without a font there is nothing to measure; rather the full size
            // than everything needlessly small.
            return 0.0;
        }

        $box = imagettfbbox($size, 0, $font, $text);

        return $box === false ? 0.0 : abs($box[2] - $box[0]);
    }
}
