<?php

namespace App\Planner\Filament\Resources\Teams\Schemas;

use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Resources\Teams\Pages\EditTeam;
use App\Planner\Filament\Resources\Teams\RelationManagers\SourcesRelationManager;
use App\Planner\Filament\Support\LockedFeature;
use App\Planner\Models\Team;
use App\Planner\Support\Weekday;
use App\Support\Tenancy\CurrentClub;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Team settings. Module-specific parts only appear when the club has the
 * module: duty settings (duties), volunteer points (volunteer_points) and the
 * locker room category (locker_rooms).
 */
class TeamForm
{
    /** Values understood by LockerRoomPlanner. */
    public const LOCKER_ROOM_CATEGORIES = ['boys_men', 'girls_women'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make(__('Teaminstellingen'))->persistTabInQueryString('section')->tabs([
                Tab::make(__('Team'))
                    ->icon(Heroicon::OutlinedIdentification)
                    ->schema([
                        Section::make(__('Teamgegevens'))
                            ->description(__('Naam, teamcode en kleedkamerindeling zoals gebruikt in de planning.'))
                            ->columns(2)
                            ->schema([
                                TextInput::make('code')
                                    ->label(__('Teamcode'))
                                    ->required()
                                    ->maxLength(10)
                                    ->placeholder('HS1')
                                    ->helperText(__('Bijv. HS1, JA1 of MA1')),

                                TextInput::make('name')
                                    ->label(__('Naam'))
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder(fn (): string => trim((app(CurrentClub::class)->get()->name ?? '').' HS 1')),

                                Select::make('locker_room_category')
                                    ->label(__('Kleedkamercategorie'))
                                    ->options(self::lockerRoomOptions())
                                    ->native(false)
                                    ->disabled(fn (): bool => LockedFeature::isLocked(PlannerFeature::LockerRooms))
                                    ->hintAction(LockedFeature::hintAction(PlannerFeature::LockerRooms))
                                    ->helperText(__('Wordt gebruikt om kleedkamerconflicten te voorkomen.')),
                            ]),
                    ]),
                Tab::make(__('Standaardplanning'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->schema([
                        Section::make(__('Voorkeursmoment en locatie'))
                            ->description(__('Deze waarden worden automatisch voorgesteld bij het inplannen van thuiswedstrijden.'))
                            ->columns(2)
                            ->schema([
                                Select::make('default_weekday')
                                    ->label(__('Standaard speeldag'))
                                    ->options(self::weekdayOptions())
                                    ->native(false),

                                TimePicker::make('default_time')
                                    ->label(__('Standaard tijd'))
                                    ->native(false)
                                    ->displayFormat('H:i')
                                    ->seconds(false),

                                Select::make('default_venue_id')
                                    ->label(__('Standaard sporthal'))
                                    ->relationship('defaultVenue', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false),

                                Select::make('default_court')
                                    ->label(__('Standaard veld (positie)'))
                                    ->options(array_combine(range(1, 12), array_map('strval', range(1, 12))))
                                    ->native(false)
                                    ->helperText(__('Positie van het veld binnen de gekozen hal.')),

                                TextInput::make('default_match_duration')
                                    ->label(__('Standaard wedstrijdduur'))
                                    ->numeric()
                                    ->minValue(60)
                                    ->suffix(__('minuten'))
                                    ->maxValue(180),
                            ]),
                        Section::make(__('Bijzondere veldindeling'))
                            ->schema([
                                Toggle::make('plays_on_two_courts')
                                    ->label(__('Speelt op 2 velden (CenterCourt)'))
                                    ->helperText(__('Reserveert automatisch een tweede veld voor CenterCourt.'))
                                    ->default(false),
                            ]),
                    ]),
                Tab::make(__('FTZ & taken'))
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->visible(fn (): bool => self::hasFeature(PlannerFeature::Duties))
                    ->schema([
                        Section::make(__('Automatische wedstrijdtaken'))
                            ->description(__('Bepaal wat bij thuiswedstrijden automatisch wordt ingevuld.'))
                            ->columns(2)
                            ->schema([
                                Toggle::make('scores_own_match')
                                    ->label(__('Telt eigen wedstrijd'))
                                    ->helperText(__('Eigen teller automatisch invullen bij inplannen'))
                                    ->default(false),

                                Toggle::make('provides_referee')
                                    ->label(__('Nevobo levert scheidsrechter'))
                                    ->helperText(__('Vult bij thuiswedstrijden automatisch Nevobo in.'))
                                    ->default(false),
                            ]),
                        Section::make(__('Meenemen in verdelingen'))
                            ->description(__('Deze keuzes bepalen in welke gezamenlijke taakverdelingen het team voorkomt.'))
                            ->columns(2)
                            ->schema([
                                Toggle::make('include_in_duties')
                                    ->label(__('Meenemen in FTZ'))
                                    ->helperText(__('Team mag door de FTZ-rule-engine worden ingepland.'))
                                    ->default(true),

                                Toggle::make('include_volunteers')
                                    ->label(__('Meenemen in vrijwilligerspunten'))
                                    ->visible(fn (): bool => self::hasFeature(PlannerFeature::VolunteerPoints))
                                    ->default(true),
                            ]),
                    ]),
                Tab::make(__('Puntenstand'))
                    ->icon(Heroicon::OutlinedScale)
                    ->visible(fn (): bool => self::hasFeature(PlannerFeature::VolunteerPoints))
                    ->schema([
                        Section::make(__('Vrijwilligerspunten'))
                            ->description(__('Verbruikt is de inzet die het team ontvangt; geleverd is de inzet die het team voor anderen uitvoert.'))
                            ->columns(2)
                            ->schema([
                                TextInput::make('volunteer_points_used')->label(__('Ontvangen / verbruikt'))->numeric()->minValue(0)->suffix(__('punten'))->default(0),
                                TextInput::make('volunteer_points_earned')->label(__('Geleverd'))->numeric()->minValue(0)->suffix(__('punten'))->default(0),
                            ]),
                    ]),
                // The sources live in a tab; a new team has no relation yet, so
                // the tab stays hidden until the team is saved.
                Tab::make(__('Wedstrijdbronnen'))
                    ->icon(Heroicon::OutlinedRss)
                    ->visible(fn (?Team $record): bool => $record !== null)
                    ->schema([
                        Livewire::make(SourcesRelationManager::class, fn (Team $record): array => [
                            'ownerRecord' => $record,
                            'pageClass' => EditTeam::class,
                        ])->key('team-sources'),
                    ]),
            ])->columnSpanFull(),
        ]);
    }

    /** @return array<string, string> */
    public static function lockerRoomOptions(): array
    {
        return ['boys_men' => __('Jongens / heren'), 'girls_women' => __('Meiden / dames')];
    }

    /** @return array<string, string> 'Ma' => 'Maandag', ... (stored as abbreviation) */
    private static function weekdayOptions(): array
    {
        return array_combine(
            Weekday::ABBREVIATIONS,
            array_map(fn (string $name): string => __(ucfirst($name)), Weekday::NAMES),
        );
    }

    private static function hasFeature(PlannerFeature $feature): bool
    {
        return (bool) app(CurrentClub::class)->get()?->hasFeature($feature);
    }
}
