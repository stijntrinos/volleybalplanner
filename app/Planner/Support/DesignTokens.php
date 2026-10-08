<?php

namespace App\Planner\Support;

/**
 * The Volleybalplanner design system (tokens.json of the Claude Design
 * system) as Filament colour palettes. planner.css carries the same values
 * as CSS variables; the two must stay in step.
 */
final class DesignTokens
{
    public const FONT = 'Outfit';

    /** Planner teal: primary action, active state, links. */
    public const BRAND = '#0B7F8A';

    /** @return array<string, array<int, string>> */
    public static function palettes(): array
    {
        return [
            'primary' => self::brand(),
            'success' => self::brand(),
            'gray' => self::gray(),
            'warning' => [
                50 => '#FFF8F0',
                100 => '#FDF0E1',
                200 => '#F9DCBC',
                300 => '#F2BD88',
                400 => '#E7944C',
                500 => '#D97420',
                600 => '#C2570C',
                700 => '#8A4B0F',
                800 => '#6F3C0E',
                900 => '#5A320F',
                950 => '#331A06',
            ],
            'danger' => [
                50 => '#FDF2F2',
                100 => '#FBE3E3',
                200 => '#F6C5C5',
                300 => '#EE9A9A',
                400 => '#E36666',
                500 => '#D23B3B',
                600 => '#B32424',
                700 => '#921C1C',
                800 => '#781B1B',
                900 => '#631C1C',
                950 => '#380A0A',
            ],
            'info' => self::brand(),
        ];
    }

    /** @return array<int, string> */
    private static function brand(): array
    {
        return [
            50 => '#F0F9FA',
            100 => '#E3F2F3',
            200 => '#BFDDE0',
            300 => '#9FDCE0',
            400 => '#5FD0D3',
            500 => '#2AA6AF',
            600 => '#0B7F8A',
            700 => '#08646D',
            800 => '#0A5159',
            900 => '#0B4046',
            950 => '#072A2E',
        ];
    }

    /** @return array<int, string> */
    private static function gray(): array
    {
        return [
            50 => '#F3F6F8',
            100 => '#E9EEF1',
            200 => '#D5DEE4',
            300 => '#B8C4CC',
            400 => '#7A8893',
            500 => '#4A5D6B',
            600 => '#3A4C5A',
            700 => '#2C3C4B',
            800 => '#1C3041',
            900 => '#10293B',
            950 => '#0B1D2A',
        ];
    }
}
