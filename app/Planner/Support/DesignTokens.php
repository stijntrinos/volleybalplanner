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

    /** Slate: primary action, active state, links. */
    public const BRAND = '#2F4858';

    /** @return array<string, array<int, string>> */
    public static function palettes(): array
    {
        return [
            'primary' => self::brand(),
            'success' => self::mint(),
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
            50 => '#F1F5F7',
            100 => '#E1EAEE',
            200 => '#C3D3DB',
            300 => '#9BB3C0',
            400 => '#6E8C9D',
            500 => '#4D6A7B',
            600 => '#2F4858',
            700 => '#283E4C',
            800 => '#213440',
            900 => '#1B2A34',
            950 => '#111C23',
        ];
    }

    /** @return array<int, string> */
    private static function mint(): array
    {
        return [
            50 => '#F3FEF9',
            100 => '#DDFBEF',
            200 => '#B9F3DB',
            300 => '#86E6C0',
            400 => '#4ED0A0',
            500 => '#27B384',
            600 => '#1A916A',
            700 => '#187356',
            800 => '#175C46',
            900 => '#154C3B',
            950 => '#072B21',
        ];
    }

    /** @return array<int, string> */
    private static function gray(): array
    {
        return [
            50 => '#F3F6F7',
            100 => '#E7EDF0',
            200 => '#D6DFE3',
            300 => '#B6C4CB',
            400 => '#7D909A',
            500 => '#526672',
            600 => '#40535F',
            700 => '#31424D',
            800 => '#23323C',
            900 => '#15232C',
            950 => '#0D171D',
        ];
    }
}
