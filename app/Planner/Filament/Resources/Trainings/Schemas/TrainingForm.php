<?php

namespace App\Planner\Filament\Resources\Trainings\Schemas;

use App\Planner\Enums\Source;
use App\Planner\Filament\Resources\Trainings\TrainingResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TrainingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            Section::make(__('Wie en wanneer'))
                ->description(__('Koppel het vaste wekelijkse moment aan een team en trainingsdag.'))
                ->columns(12)
                ->schema([
                    Select::make('team_id')
                        ->label(__('Team'))
                        ->relationship('team', 'name')
                        ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->code} · {$record->name}")
                        ->searchable(['code', 'name'])
                        ->preload()
                        ->placeholder(__('Algemene training zonder team'))
                        ->helperText(__('Zonder team wordt het moment wel getoond, maar niet gebruikt voor teamspecifieke FTZ-controles.'))
                        ->columnSpan(['default' => 12, 'md' => 8]),
                    Select::make('weekday')
                        ->label(__('Trainingsdag'))
                        ->options(TrainingResource::weekdayOptions())
                        ->required()
                        ->native(false)
                        ->columnSpan(['default' => 12, 'md' => 4]),
                    TextInput::make('start_time')->label(__('Begintijd'))->type('time')->step(60)->required()
                        ->columnSpan(['default' => 6, 'md' => 3]),
                    TextInput::make('end_time')->label(__('Eindtijd'))->type('time')->step(60)->required()
                        ->rules(['after:start_time'])
                        ->helperText(__('Moet na de begintijd liggen.'))
                        ->columnSpan(['default' => 6, 'md' => 3]),
                ])->columnSpanFull(),
            Section::make(__('Waar'))
                ->description(__('Kies de trainingslocatie en de gebruikte veldindeling.'))
                ->columns(12)
                ->schema([
                    Select::make('venue_id')
                        ->label(__('Sporthal'))
                        ->relationship('venue', 'name', fn ($query) => $query->where('is_active', true)->where('for_trainings', true))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpan(['default' => 12, 'md' => 7]),
                    TextInput::make('court')
                        ->label(__('Veldnummer'))
                        ->numeric()->minValue(1)->maxValue(12)->required()
                        ->columnSpan(['default' => 6, 'md' => 2]),
                    TextInput::make('court_type')
                        ->label(__('Veldindeling'))
                        ->placeholder(__('Bijv. heel of half'))
                        ->columnSpan(['default' => 6, 'md' => 3]),
                ])->columnSpanFull(),
            Section::make(__('Herkomst en toelichting'))
                ->description(__('Technische herkomst en eventuele bijzonderheden voor planners.'))
                ->columns(12)
                ->schema([
                    Select::make('source')
                        ->label(__('Herkomst'))
                        ->options([Source::Manual->value => __('Handmatig'), Source::Import->value => __('Import')])
                        ->default(Source::Manual->value)->required()
                        ->helperText(__('Geïmporteerde regels kunnen bij een volgende import worden bijgewerkt.'))
                        ->columnSpan(['default' => 12, 'md' => 4]),
                    Textarea::make('notes')
                        ->label(__('Interne opmerking'))
                        ->placeholder(__('Bijv. alleen de helft van veld 2'))
                        ->rows(3)->maxLength(1000)
                        ->columnSpan(['default' => 12, 'md' => 8]),
                ])->columnSpanFull(),
        ]);
    }
}
