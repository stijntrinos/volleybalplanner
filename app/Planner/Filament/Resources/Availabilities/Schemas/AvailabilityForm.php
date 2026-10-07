<?php

namespace App\Planner\Filament\Resources\Availabilities\Schemas;

use App\Planner\Enums\AvailabilityScope;
use App\Planner\Enums\AvailabilityStatus;
use App\Planner\Models\Court;
use App\Planner\Support\DateFormat;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AvailabilityForm
{
    public static function configure(Schema $schema): Schema
    {
        $is = fn (AvailabilityScope $scope): Closure => fn (callable $get): bool => self::scope($get('scope')) === $scope;

        return $schema
            ->columns(12)
            ->components([
                Section::make(__('Locatie'))
                    ->description(__('Kies of de regel voor een volledige sporthal of één specifiek veld geldt.'))
                    ->columns(2)
                    ->schema([
                        Select::make('venue_id')
                            ->label(__('Sporthal'))
                            ->relationship('venue', 'name', fn (Builder $query) => $query->where('is_managed', true))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('court_id', null)),
                        Select::make('court_id')
                            ->label(__('Veld'))
                            ->options(fn (callable $get) => $get('venue_id')
                                ? Court::query()->where('venue_id', $get('venue_id'))->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')
                                : [])
                            ->native(false)
                            ->placeholder(__('Alle velden in deze sporthal'))
                            ->helperText(__('Laat leeg om de regel op de hele sporthal toe te passen.')),
                    ])
                    ->columnSpanFull(),

                Section::make(__('Geldigheid en status'))
                    ->description(__('Stel in wanneer de regel geldt en wat er op dat moment mogelijk is.'))
                    ->columns(12)
                    ->schema([
                        Select::make('scope')
                            ->label(__('Herhaling'))
                            ->options([
                                AvailabilityScope::Date->value => __('Een specifieke datum'),
                                AvailabilityScope::Weekday->value => __('Iedere week op een vaste dag'),
                                AvailabilityScope::Period->value => __('Een aaneengesloten periode'),
                            ])
                            ->native(false)
                            ->default(AvailabilityScope::Date->value)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (callable $set, mixed $state): void {
                                $scope = self::scope($state);
                                if ($scope !== AvailabilityScope::Date) {
                                    $set('date', null);
                                }
                                if ($scope !== AvailabilityScope::Weekday) {
                                    $set('weekday', null);
                                }
                                if ($scope !== AvailabilityScope::Period) {
                                    $set('period_start', null);
                                    $set('period_end', null);
                                }
                            })
                            ->columnSpan(['default' => 12, 'md' => 6]),
                        Select::make('status')
                            ->label(__('Beschikbaarheid'))
                            ->options([
                                AvailabilityStatus::Available->value => __('Beschikbaar'),
                                AvailabilityStatus::Partial->value => __('Gedeeltelijk beschikbaar'),
                                AvailabilityStatus::Closed->value => __('Gesloten'),
                            ])
                            ->native(false)
                            ->default(AvailabilityStatus::Closed->value)
                            ->helperText(__('Een specifiekere regel overschrijft een algemene regel voor dezelfde datum.'))
                            ->required()
                            ->columnSpan(['default' => 12, 'md' => 6]),
                        DatePicker::make('date')
                            ->label(__('Datum'))
                            ->native(false)
                            ->displayFormat('d-m-Y')
                            ->visible($is(AvailabilityScope::Date))
                            ->required($is(AvailabilityScope::Date))
                            ->columnSpan(['default' => 12, 'md' => 6]),
                        Select::make('weekday')
                            ->label(__('Weekdag'))
                            ->options([
                                1 => __('Maandag'), 2 => __('Dinsdag'), 3 => __('Woensdag'), 4 => __('Donderdag'),
                                5 => __('Vrijdag'), 6 => __('Zaterdag'), 0 => __('Zondag'),
                            ])
                            ->native(false)
                            ->visible($is(AvailabilityScope::Weekday))
                            ->required($is(AvailabilityScope::Weekday))
                            ->columnSpan(['default' => 12, 'md' => 6]),
                        DatePicker::make('period_start')
                            ->label(__('Van'))
                            ->native(false)
                            ->displayFormat('d-m-Y')
                            ->visible($is(AvailabilityScope::Period))
                            ->required($is(AvailabilityScope::Period))
                            ->columnSpan(['default' => 12, 'md' => 6]),
                        DatePicker::make('period_end')
                            ->label(__('Tot en met'))
                            ->native(false)
                            ->displayFormat('d-m-Y')
                            ->visible($is(AvailabilityScope::Period))
                            ->required($is(AvailabilityScope::Period))
                            ->afterOrEqual('period_start')
                            ->columnSpan(['default' => 12, 'md' => 6]),
                        self::timeField('start_time', __('Vanaf'))
                            ->helperText(__('Laat leeg wanneer de regel de hele dag geldt.'))
                            ->requiredWith('end_time'),
                        self::timeField('end_time', __('Tot'))
                            ->requiredWith('start_time')
                            ->rule(static fn (callable $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                $start = DateFormat::time($get('start_time'));
                                if ($start && $value && $value <= $start) {
                                    $fail(__('De eindtijd moet na de begintijd liggen.'));
                                }
                            }),
                    ])
                    ->columnSpanFull(),

                Section::make(__('Toelichting'))
                    ->description(__('Optioneel, bijvoorbeeld de reden van een sluiting of beperkte capaciteit.'))
                    ->schema([
                        Textarea::make('note')
                            ->label(__('Interne notitie'))
                            ->placeholder(__('Bijv. alleen veld 1 beschikbaar wegens onderhoud'))
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Typing a time is faster than picking it from a list: "1830" is enough.
     * The half hours stay below it as a reminder, but an odd time like 18:45
     * can simply be typed.
     */
    private static function timeField(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->placeholder('18:30')
            ->mask('99:99')
            ->datalist(self::halfHours())
            ->extraInputAttributes(['inputmode' => 'numeric', 'class' => 'font-mono'])
            ->formatStateUsing(fn (mixed $state): ?string => DateFormat::time($state))
            ->dehydrateStateUsing(fn (mixed $state): ?string => DateFormat::time($state))
            ->rule('date_format:H:i')
            ->validationMessages(['date_format' => __('Vul een tijd in als 18:30.')])
            ->columnSpan(['default' => 6, 'md' => 6]);
    }

    /** @return list<string> */
    private static function halfHours(): array
    {
        $times = [];
        for ($minutes = 7 * 60; $minutes <= 23 * 60 + 30; $minutes += 30) {
            $times[] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        return $times;
    }

    private static function scope(mixed $state): ?AvailabilityScope
    {
        return $state instanceof AvailabilityScope ? $state : AvailabilityScope::tryFrom((string) $state);
    }
}
