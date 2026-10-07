<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** How the requester wants to be contacted. */
enum ContactPreference: string implements HasLabel
{
    case Email = 'email';

    case Whatsapp = 'whatsapp';

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => __('E-mail'),
            self::Whatsapp => __('WhatsApp'),
        };
    }
}
