<?php

namespace App\Filament\Platform\Resources\Clubs\Schemas;

use App\Planner\Features\PlannerFeature;
use App\Support\Tenancy\ReservedSlugs;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ClubForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Vereniging')
                    ->description('Naam en adres van de werkruimte. De slug komt terug in de publieke programma- en verzoekpagina’s.')
                    ->icon('heroicon-o-building-office-2')
                    ->aside()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Naam')
                            ->placeholder('VC Voorbeeld')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                if (blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            })
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->prefix(Str::after(url('/'), '://').'/')
                            ->helperText('Alleen kleine letters, cijfers en streepjes.')
                            ->required()
                            ->maxLength(64)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->notIn(ReservedSlugs::ALL)
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Actief')
                            ->helperText('Leden van een inactieve club kunnen niet inloggen, de publieke pagina’s zijn offline en de Nevobo-sync slaat de club over.')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
                Section::make('Modules')
                    ->description('De basisplanning (thuiswedstrijden, sporthallen, teams, Nevobo-sync en exports) is altijd beschikbaar. Zet hier de extra modules aan.')
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
            ]);
    }
}
