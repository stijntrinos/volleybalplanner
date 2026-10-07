<?php

namespace App\Planner\Filament\Resources\ScheduleVersions\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ScheduleVersionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            Section::make(__('Versiegegevens'))
                ->description(__('Gebruik een herkenbare naam, zodat deze versie later eenvoudig terug te vinden is.'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('Versienaam'))
                        ->placeholder(__('Bijv. Concept competitie 2026–2027'))
                        ->helperText(__('De versie wordt altijd eerst als concept opgeslagen.'))
                        ->required()
                        ->maxLength(255),
                    Textarea::make('note')
                        ->label(__('Interne notitie'))
                        ->placeholder(__('Beschrijf de belangrijkste wijzigingen in deze versie'))
                        ->helperText(__('Alleen zichtbaar voor beheerders van de planning.'))
                        ->rows(4)
                        ->maxLength(2000),
                ])
                ->columnSpanFull(),
        ]);
    }
}
