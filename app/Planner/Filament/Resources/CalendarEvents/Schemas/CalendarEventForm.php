<?php

namespace App\Planner\Filament\Resources\CalendarEvents\Schemas;

use App\Planner\Models\ActivityColor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CalendarEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            Section::make(__('Activiteit en type'))
                ->description(__('Leg vast wat er plaatsvindt en onder welk kalendertype de activiteit valt.'))
                ->columns(12)
                ->schema([
                    TextInput::make('name')
                        ->label(__('Activiteit'))
                        ->placeholder(__('Bijv. Beker 1/4 finale'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(['default' => 12, 'md' => 8]),
                    Select::make('type')
                        ->label(__('Type'))
                        ->options(fn () => ActivityColor::query()->orderBy('type')->pluck('type', 'type'))
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->required()
                        ->helperText(__('De kleur wordt bepaald bij Plannerkleuren.'))
                        ->columnSpan(['default' => 12, 'md' => 4]),
                    DatePicker::make('start_date')
                        ->label(__('Begindatum'))
                        ->native(false)
                        ->displayFormat('d-m-Y')
                        ->helperText(__('Eerste dag waarop deze activiteit van toepassing is.'))
                        ->required()
                        ->columnSpan(['default' => 12, 'md' => 6]),
                    DatePicker::make('end_date')
                        ->label(__('Tot en met'))
                        ->native(false)
                        ->displayFormat('d-m-Y')
                        ->helperText(__('Gebruik dezelfde datum voor een activiteit van één dag.'))
                        ->required()
                        ->afterOrEqual('start_date')
                        ->columnSpan(['default' => 12, 'md' => 6]),
                ])
                ->columnSpanFull(),
        ]);
    }
}
