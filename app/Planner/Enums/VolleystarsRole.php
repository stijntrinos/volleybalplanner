<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** Task within a VolleyStars block. */
enum VolleystarsRole: string implements HasLabel
{
    case Setup = 'setup';

    case Teardown = 'teardown';

    case Scoring = 'scoring';

    public function getLabel(): string
    {
        return match ($this) {
            self::Setup => __('Opbouwen'),
            self::Teardown => __('Afbouwen'),
            self::Scoring => __('Tellen'),
        };
    }
}
