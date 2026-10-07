<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** Whether a planned home match still has to be reported to Nevobo. */
enum NevoboStatus: string implements HasLabel
{
    case NotApplicable = 'not_applicable';

    case Open = 'open';

    case Applied = 'applied';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotApplicable => __('n.v.t.'),
            self::Open => __('Open'),
            self::Applied => __('Doorgevoerd'),
        };
    }
}
