<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** What a visitor of the public site asks for in the contact form. */
enum ContactTopic: string implements HasLabel
{
    case Demo = 'demo';
    case Question = 'vraag';
    case Quote = 'offerte';
    // Sent from the upgrade prompt inside the planner, never from the site.
    case Upgrade = 'upgrade';

    /** @return list<self> the topics a visitor of the public site can choose */
    public static function publicCases(): array
    {
        return [self::Demo, self::Question, self::Quote];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Demo => 'Demo aanvragen',
            self::Question => 'Een vraag stellen',
            self::Quote => 'Offerte voor groot pakket',
            self::Upgrade => 'Upgrade van pakket',
        };
    }

    /** The text on the submit button once this topic is chosen. */
    public function submitLabel(): string
    {
        return match ($this) {
            self::Demo => 'Demo aanvragen',
            self::Question => 'Vraag versturen',
            self::Quote => 'Offerte aanvragen',
            self::Upgrade => 'Upgrade aanvragen',
        };
    }
}
