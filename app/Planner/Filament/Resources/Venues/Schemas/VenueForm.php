<?php

namespace App\Planner\Filament\Resources\Venues\Schemas;

use App\Planner\Enums\Source;
use App\Planner\Features\PlanLimit;
use App\Planner\Features\PlanLimits;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Support\LockedFeature;
use App\Planner\Models\Venue;
use App\Support\Tenancy\CurrentClub;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class VenueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                Section::make(__('Sporthalgegevens'))
                    ->description(__('De herkenbare naam, locatie en Nevobo-koppeling van deze sporthal.'))
                    ->columns(12)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Naam sporthal'))
                            ->placeholder(__('Bijv. Sporthal De Wiel'))
                            ->required()
                            ->maxLength(255)
                            // Unique per club and city; the club scope applies to this query.
                            ->scopedUnique(
                                ignoreRecord: true,
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->where('city', $get('city')),
                            )
                            ->validationMessages([
                                'unique' => __('Er bestaat al een sporthal met deze naam in deze plaats.'),
                            ])
                            ->columnSpan(['default' => 12, 'md' => 5]),

                        TextInput::make('venue_code')
                            ->label(__('Nevobo-zaalcode'))
                            ->placeholder(__('Optioneel'))
                            ->maxLength(20)
                            ->scopedUnique(ignoreRecord: true)
                            ->validationMessages([
                                'unique' => __('Deze zaalcode is al aan een andere sporthal gekoppeld.'),
                            ])
                            ->helperText(__('Voor automatische koppeling bij imports.'))
                            ->columnSpan(['default' => 12, 'md' => 3]),

                        Select::make('source')
                            ->label(__('Herkomst'))
                            ->options([
                                Source::Manual->value => __('Handmatig toegevoegd'),
                                Source::MatchImport->value => __('Wedstrijdimport'),
                            ])
                            ->required()
                            ->native(false)
                            ->default(Source::Manual->value)
                            ->columnSpan(['default' => 12, 'md' => 4]),

                        TextInput::make('city')
                            ->label(__('Plaats'))
                            ->maxLength(255)
                            ->columnSpan(['default' => 12, 'md' => 4]),

                        TextInput::make('address')
                            ->label(__('Adres'))
                            ->placeholder(__('Straat en huisnummer'))
                            ->maxLength(255)
                            ->columnSpan(['default' => 12, 'md' => 8]),
                    ])
                    ->columnSpanFull(),

                Section::make(__('Gebruik in de planner'))
                    ->description(__('Actief bepaalt of de locatie gebruikt mag worden; de overige opties bepalen waar deze verschijnt.'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('Sporthal is actief'))
                            ->helperText(__('Schakel uit om een oude locatie te bewaren zonder deze nog te gebruiken.'))
                            ->default(true),

                        Toggle::make('is_managed')
                            ->label(__('Bij thuiswedstrijden tonen'))
                            ->helperText(__('Maakt de locatie zichtbaar bij Thuiswedstrijden en bij beschikbaarheidsregels.'))
                            ->default(false)
                            ->rules([fn (Get $get, ?Venue $record): Closure => self::venueLimitRule((bool) $get('is_active'), $record)]),

                        Toggle::make('has_duties')
                            ->label(__('FTZ-locatie'))
                            ->helperText(__('Hier worden fluit-, tel- en zaaldiensten verdeeld.'))
                            ->visible(fn (): bool => self::hasFeature(PlannerFeature::Duties))
                            ->default(false),

                        Toggle::make('show_locker_rooms_on_schedule')
                            ->label(__('Kleedkamers op het dagschema'))
                            ->helperText(__('Deelt kleedkamers in en toont ze bij elke wedstrijd in het geëxporteerde dagschema.'))
                            ->disabled(fn (): bool => LockedFeature::isLocked(PlannerFeature::LockerRooms))
                            ->hintAction(LockedFeature::hintAction(PlannerFeature::LockerRooms))
                            ->default(false),

                        Toggle::make('for_matches')
                            ->label(__('Wedstrijden'))
                            ->helperText(__('Mag voor wedstrijden worden gebruikt.'))
                            ->default(false),

                        Toggle::make('for_trainings')
                            ->label(__('Trainingen'))
                            ->helperText(__('Mag voor trainingen worden gebruikt.'))
                            ->visible(fn (): bool => self::hasFeature(PlannerFeature::Trainings))
                            ->default(true),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * A sporthal in the planner (active and shown at home matches) counts
     * against the plan; switching one on past the limit is refused, so the
     * toggles can't get around the locked "add" button.
     */
    private static function venueLimitRule(bool $otherToggleOn, ?Venue $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($otherToggleOn, $record): void {
            $counts = (bool) $value && $otherToggleOn;
            $countedAlready = $record !== null && $record->getOriginal('is_active') && $record->getOriginal('is_managed');

            if ($counts && !$countedAlready && PlanLimits::reached(PlanLimit::Venues)) {
                $max = (int) app(CurrentClub::class)->get()?->planLimit(PlanLimit::Venues);
                $fail(PlanLimit::Venues->reachedMessage($max).' '.__('Upgrade je pakket om meer sporthallen in de planner te zetten.'));
            }
        };
    }

    private static function hasFeature(PlannerFeature $feature): bool
    {
        return (bool) app(CurrentClub::class)->get()?->hasFeature($feature);
    }
}
