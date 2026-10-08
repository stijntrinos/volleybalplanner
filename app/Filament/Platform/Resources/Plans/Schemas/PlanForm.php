<?php

namespace App\Filament\Platform\Resources\Plans\Schemas;

use App\Planner\Features\PlanLimit;
use App\Planner\Features\PlannerFeature;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Pakket')
                    ->description('Naam, prijs en omschrijving zoals ze op de prijzenpagina staan.')
                    ->icon('heroicon-o-credit-card')
                    ->aside()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Naam')
                            ->placeholder('Vereniging')
                            ->required()
                            ->maxLength(60)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                if (blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(60)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->unique(ignoreRecord: true),
                        TextInput::make('tagline')
                            ->label('Voor wie')
                            ->placeholder('Voor verenigingen tot 30 teams, in maximaal drie sporthallen.')
                            ->maxLength(160)
                            ->columnSpanFull(),
                        TextInput::make('price')
                            ->label('Prijs per seizoen')
                            ->prefix('€')
                            ->suffix('excl. btw')
                            ->integer()
                            ->minValue(0)
                            ->helperText('Leeg laten voor “Op aanvraag”.'),
                        TextInput::make('sort_order')
                            ->label('Volgorde')
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->helperText('Van klein naar groot; upgrades gaan naar het volgende pakket dat de module heeft.'),
                        Toggle::make('is_featured')
                            ->label('Uitgelicht als “Meest gekozen”'),
                        Toggle::make('is_public')
                            ->label('Tonen op de prijzenpagina')
                            ->default(true),
                    ]),
                Section::make('Limieten')
                    ->description('Leeg laten is onbeperkt. Een club die er al overheen zit houdt alles, maar kan niets meer toevoegen.')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->aside()
                    ->columns(3)
                    ->schema(array_map(
                        fn (PlanLimit $limit): TextInput => TextInput::make($limit->column())
                            ->label($limit->getLabel())
                            ->integer()
                            ->minValue(1)
                            ->placeholder('Onbeperkt'),
                        PlanLimit::cases(),
                    )),
                Section::make('Modules')
                    ->description('De basisplanning (thuiswedstrijden, sporthallen, teams, Nevobo-sync en exports) zit in elk pakket.')
                    ->icon('heroicon-o-puzzle-piece')
                    ->aside()
                    ->schema([
                        CheckboxList::make('features')
                            ->hiddenLabel()
                            ->label('Modules')
                            ->options(PlannerFeature::class)
                            ->descriptions(collect(PlannerFeature::cases())
                                ->mapWithKeys(fn (PlannerFeature $feature): array => [$feature->value => $feature->getDescription()])
                                ->all())
                            ->bulkToggleable()
                            ->columns(['default' => 1, 'lg' => 2])
                            ->gridDirection('row')
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    $unmet = PlannerFeature::unmetRequirements(array_map('strval', (array) $value));

                                    if ($unmet !== []) {
                                        $fail(implode(' ', $unmet));
                                    }
                                },
                            ]),
                    ]),
                Section::make('Prijzenpagina')
                    ->description('De punten onder de prijs. Het eerste pakket krijgt “Wat je krijgt”, de volgende “Alles uit … , plus”.')
                    ->icon('heroicon-o-list-bullet')
                    ->aside()
                    ->schema([
                        Repeater::make('highlights')
                            ->hiddenLabel()
                            ->simple(TextInput::make('text')->required()->maxLength(80))
                            ->addActionLabel('Punt toevoegen')
                            ->reorderable()
                            ->defaultItems(0),
                    ]),
            ]);
    }
}
