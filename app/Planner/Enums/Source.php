<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** Where a venue, court, team or training came from. */
enum Source: string implements HasLabel
{
    case Manual = 'manual';

    case Import = 'import';

    case MatchImport = 'match_import';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => __('Handmatig'),
            self::Import => __('Import'),
            self::MatchImport => __('Wedstrijdimport'),
        };
    }
}
