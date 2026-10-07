<?php

namespace App\Filament\Platform\Resources\Clubs\Schemas;

use App\Planner\Features\PlannerFeature;
use App\Support\Tenancy\ReservedSlugs;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ClubForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Naam')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                    if (blank($get('slug'))) {
                        $set('slug', Str::slug((string) $state));
                    }
                }),
            TextInput::make('slug')
                ->label('Slug (URL)')
                ->helperText('Gebruikt in publieke adressen, bijvoorbeeld /{slug}/wedstrijden.')
                ->required()
                ->maxLength(64)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->notIn(ReservedSlugs::ALL)
                ->unique(ignoreRecord: true),
            Toggle::make('is_active')
                ->label('Actief')
                ->default(true),
            CheckboxList::make('features')
                ->label('Modules')
                ->options(PlannerFeature::class)
                ->columns(2)
                ->columnSpanFull()
                ->rules([
                    fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        $unmet = PlannerFeature::unmetRequirements(array_map('strval', (array) $value));

                        if ($unmet !== []) {
                            $fail(implode(' ', $unmet));
                        }
                    },
                ]),
        ]);
    }
}
