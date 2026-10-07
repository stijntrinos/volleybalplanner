<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** What the day planner does when two matches overlap on a court. */
enum OverlapPolicy: string implements HasLabel
{
    case Deny = 'deny';

    case Warn = 'warn';

    case Allow = 'allow';

    public function getLabel(): string
    {
        return match ($this) {
            self::Deny => __('Niet toestaan'),
            self::Warn => __('Toestaan met waarschuwing'),
            self::Allow => __('Altijd toestaan'),
        };
    }
}
