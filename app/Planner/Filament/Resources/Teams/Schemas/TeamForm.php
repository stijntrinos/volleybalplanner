<?php

namespace App\Planner\Filament\Resources\Teams\Schemas;

use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Resources\Teams\Pages\EditTeam;
use App\Planner\Filament\Resources\Teams\RelationManagers\AbsencesRelationManager;
use App\Planner\Filament\Resources\Teams\RelationManagers\SourcesRelationManager;
use App\Planner\Filament\Support\LockedFeature;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Team;
use App\Planner\Services\Duties\DutyRules;
use App\Planner\Services\Duties\PointsBalance;
use App\Planner\Services\DutyTallyService;
use App\Planner\Support\Weekday;
use App\Support\Tenancy\CurrentClub;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                                    // A new team gets the default planning exceptions of
                                    // its age class; after that the committee decides.
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?string $state, Set $set, string $operation): void {
                                        if ($operation !== 'create') {
                                            return;
                                        }
                                        foreach (Team::defaultPlanningRules($state) as $field => $value) {
                                            $set($field, $value);
                                        }
                                    })
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
                                    ->helperText(__('Het team krijgt de teldienst bij zijn eigen thuiswedstrijd.'))
                                    ->live()
                                    ->default(false),

                                DatePicker::make('scores_own_match_until')
                                    ->label(__('Telt eigen wedstrijd tot en met'))
                                    ->native(false)
                                    ->displayFormat('d-m-Y')
                                    ->visible(fn (Get $get): bool => (bool) $get('scores_own_match'))
                                    ->helperText(__('Leeg laten voor het hele seizoen.')),

                                Toggle::make('provides_referee')
                                    ->label(__('Nevobo levert scheidsrechter'))
                                    ->helperText(__('Geen fluitdienst van de club bij de eigen thuiswedstrijd.'))
                                    ->default(false),

                                Select::make('referee_license')
                                    ->label(__('Minimale fluitlicentie'))
                                    ->options(collect(Team::REFEREE_LICENSES)->mapWithKeys(fn (string $description, string $code): array => [$code => $code.' - '.__($description)])->all())
                                    ->native(false)
                                    ->placeholder(__('Geen eis'))
                                    ->helperText(__('De licentie die je minimaal nodig hebt om een thuiswedstrijd van dit team te fluiten. Staat in de RSS-feed en op de publieke wedstrijdpagina.')),
                            ]),
                        Section::make(__('Planningsuitzonderingen'))
                            ->description(__('Deze instellingen bepalen welke diensten de FTZ-planning het team voorstelt. De commissie kan altijd een ander team kiezen.'))
                            ->columns(2)
                            ->schema([
                                Toggle::make('no_scoring_heavy_teams')
                                    ->label(__('Niet tellen bij zware wedstrijden'))
                                    ->helperText(fn (): string => ($heavy = self::rules()->scorerHeavyTeams()) !== []
                                        ? __('Geen teldienst bij thuiswedstrijden van :teams.', ['teams' => DutyRules::enumerate($heavy)])
                                        : __('Geen teldienst bij de zware wedstrijden uit de plannerinstellingen; daar staan er nu geen.')),
                                Toggle::make('no_scoring_late')
                                    ->label(fn (): string => __('Niet tellen na :time uur', ['time' => self::rules()->scorerCutoff()]))
                                    ->helperText(fn (): string => __('Geen teldienst bij wedstrijden die om :time uur of later beginnen.', ['time' => self::rules()->scorerCutoff()])),
                                Toggle::make('no_hall_duty')
                                    ->label(__('Geen zaaldiensten voorstellen'))
                                    ->helperText(__('Het team krijgt geen zaaldienst voorgesteld.')),
                                Toggle::make('never_referees')
                                    ->label(__('Fluit nooit'))
                                    ->helperText(__('Geen fluitdienst voorgesteld; wel voorrang bij tel-, zaal- en VolleyStars-diensten.')),
                                Toggle::make('referees_restricted_teams')
                                    ->label(fn (): string => ($restricted = self::rules()->restrictedRefereeTeams()) !== []
                                        ? __('Fluit bij :teams', ['teams' => DutyRules::enumerate($restricted)])
                                        : __('Fluit bij de teams met een vaste fluitploeg'))
                                    ->helperText(__('Alleen teams met deze instelling worden voorgesteld om te fluiten bij de teams met een vaste fluitploeg uit de plannerinstellingen.')),
                                Toggle::make('referees_c_youth')
                                    ->label(__('Fluit bij C-jeugd (A-jeugd)'))
                                    ->helperText(__('Bij voorkeur C-jeugd, B-jeugd alleen als niemand anders past, nooit A-jeugd of senioren.')),
                                Toggle::make('referees_youth_and_lower_women')
                                    ->label(__('Fluit alleen bij jeugd en lagere damesklassen'))
                                    ->helperText(__('Alleen fluiten bij jeugd en dames uit een lagere klasse.')),
                                TextInput::make('weekly_max')
                                    ->label(__('Weekmaximum'))
                                    ->integer()
                                    ->minValue(0)
                                    ->suffix(__('diensten'))
                                    ->placeholder(fn (): string => (string) self::settings()->max_duties_per_week)
                                    ->helperText(__('Afwijkend maximum aantal diensten per week. Leeg betekent het seizoensmaximum.')),
                                Select::make('playing_group')
                                    ->label(__('Speelgroep'))
                                    ->options(Team::playingGroups())
                                    ->native(false)
                                    ->placeholder(__('Geen speelgroep'))
                                    ->helperText(__('Bij gelijke achterstand gaat een team voor wiens speelgroep bij de dag past.')),
                            ]),
                        Section::make(__('Meenemen in verdelingen'))
                            ->description(__('Deze keuzes bepalen in welke gezamenlijke taakverdelingen het team voorkomt.'))
                            ->columns(2)
                            ->schema([
                                Toggle::make('include_in_duties')
                                    ->label(__('Meenemen in FTZ'))
                                    ->helperText(__('Het team mag door de FTZ-planning worden ingepland.'))
                                    ->default(true),

                                Toggle::make('include_volunteers')
                                    ->label(__('Meenemen in vrijwilligerspunten'))
                                    ->helperText(__('Staat dit uit, dan heeft het team geen minimum. Ingevoerde waardes blijven bewaard.'))
                                    ->visible(fn (): bool => self::hasFeature(PlannerFeature::VolunteerPoints))
                                    ->live()
                                    ->default(true),
                            ]),
                    ]),
                Tab::make(__('Puntenstand'))
                    ->icon(Heroicon::OutlinedScale)
                    ->visible(fn (): bool => self::hasFeature(PlannerFeature::VolunteerPoints))
                    ->schema([
                        Section::make(__('Vrijwilligerspunten'))
                            ->description(__('Behoefte is de inzet die het team ontvangt. Geleverd is de inzet die het team buiten de FTZ-planning om voor anderen uitvoert. Het minimum is de behoefte maal het minimumpercentage.'))
                            ->columns(2)
                            ->schema([
                                TextInput::make('volunteer_points_used')
                                    ->label(__('Behoefte'))
                                    ->integer()
                                    ->minValue(0)
                                    ->suffix(__('punten'))
                                    ->default(0)
                                    ->live(debounce: 400)
                                    ->disabled(fn (Get $get): bool => !$get('include_volunteers')),
                                TextInput::make('volunteer_points_percentage')
                                    ->label(__('Minimumpercentage'))
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->placeholder('100')
                                    ->helperText(__('Het deel van de behoefte dat het team minimaal levert. Leeg betekent 100%.'))
                                    ->live(debounce: 400)
                                    ->disabled(fn (Get $get): bool => !$get('include_volunteers')),
                                TextEntry::make('minimum')
                                    ->label(__('Minimum'))
                                    ->state(fn (Get $get): string => self::balance($get)->hasMinimum()
                                        ? __(':count punten', ['count' => self::balance($get)->minimum()])
                                        : __('Geen minimum')),
                                TextInput::make('volunteer_points_earned')
                                    ->label(__('Geleverd'))
                                    ->integer()
                                    ->minValue(0)
                                    ->suffix(__('punten'))
                                    ->default(0)
                                    ->live(debounce: 400),
                            ]),
                        Section::make(__('Stand'))
                            ->description(__('Ingepland zijn de punten van de diensten die in de gepubliceerde FTZ-planning aan het team zijn toegewezen.'))
                            ->columns(4)
                            ->schema([
                                TextEntry::make('balance_planned')
                                    ->label(__('Ingepland'))
                                    ->state(fn (Get $get, ?Team $record): string => __(':count punten', ['count' => self::balance($get, $record)->planned])),
                                TextEntry::make('balance_total')
                                    ->label(__('Totaal geleverd'))
                                    ->state(fn (Get $get, ?Team $record): string => __(':count punten', ['count' => self::balance($get, $record)->total()])),
                                TextEntry::make('balance_remaining')
                                    ->label(__('Nog in te vullen tot het minimum'))
                                    ->state(fn (Get $get, ?Team $record): string => self::balance($get, $record)->hasMinimum()
                                        ? __(':count punten', ['count' => self::balance($get, $record)->remaining()])
                                        : '-'),
                                TextEntry::make('balance_percentage')
                                    ->label(__('Percentage'))
                                    ->badge()
                                    ->color(fn (Get $get, ?Team $record): string => match (true) {
                                        !self::balance($get, $record)->hasMinimum() => 'gray',
                                        self::balance($get, $record)->isBelowMinimum() => 'warning',
                                        default => 'success',
                                    })
                                    ->state(fn (Get $get, ?Team $record): string => self::balanceText(self::balance($get, $record))),
                            ]),
                    ]),
                // A new team has no relation yet, so the tab stays hidden until
                // the team is saved.
                Tab::make(__('Afwezigheid'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->visible(fn (?Team $record): bool => $record !== null && self::hasFeature(PlannerFeature::Duties))
                    ->schema([
                        Livewire::make(AbsencesRelationManager::class, fn (Team $record): array => [
                            'ownerRecord' => $record,
                            'pageClass' => EditTeam::class,
                        ])->key('team-absences'),
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

    /**
     * The standing with the values as they are in the form now, so the
     * minimum changes along while typing.
     */
    private static function balance(Get $get, ?Team $record = null): PointsBalance
    {
        $percentage = $get('volunteer_points_percentage');

        return new PointsBalance(
            need: (int) $get('volunteer_points_used'),
            minimumPercentage: $percentage === null || $percentage === '' ? null : (int) $percentage,
            earned: (int) $get('volunteer_points_earned'),
            planned: $record ? self::planned($record) : 0,
            included: (bool) $get('include_volunteers'),
        );
    }

    private static function balanceText(PointsBalance $balance): string
    {
        return match (true) {
            !$balance->hasMinimum() => __('Geen minimum'),
            $balance->isAboveTarget() => __(':percentage (boven doel)', ['percentage' => PointsBalance::percentageText($balance->percentage())]),
            default => PointsBalance::percentageText($balance->percentage()),
        };
    }

    /** Once per request: every entry in the Standing section asks again. */
    private static function planned(Team $record): int
    {
        return once(fn (): int => app(DutyTallyService::class)->balanceOfTeam($record)->planned);
    }

    private static function settings(): PlannerSetting
    {
        return once(fn (): PlannerSetting => PlannerSetting::current());
    }

    private static function rules(): DutyRules
    {
        return new DutyRules(self::settings()->duty_rules);
    }

    private static function hasFeature(PlannerFeature $feature): bool
    {
        return (bool) app(CurrentClub::class)->get()?->hasFeature($feature);
    }
}
