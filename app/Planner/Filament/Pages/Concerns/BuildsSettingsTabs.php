<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Enums\OverlapPolicy;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Support\LockedFeature;
use App\Planner\Models\Venue;
use App\Planner\Support\TeamCode;
use App\Support\Tenancy\CurrentClub;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;

/**
 * The tabs of the planner settings form. Sections of an optional module are
 * only visible when the club has that module; hidden fields are not saved,
 * so their stored values stay as they are.
 */
trait BuildsSettingsTabs
{
    protected static function hasModule(PlannerFeature $feature): bool
    {
        return (bool) app(CurrentClub::class)->get()?->hasFeature($feature);
    }

    protected function preferencesTab(): Tab
    {
        return Tab::make(__('Mijn voorkeuren'))
            ->icon(Heroicon::OutlinedUser)
            ->schema([
                Section::make(__('Startpunt en weergave'))
                    ->description(__('Deze instellingen gelden alleen voor jouw account.'))
                    ->icon(Heroicon::OutlinedComputerDesktop)
                    ->schema([
                        Select::make('default_venue_id')
                            ->label(__('Standaard sporthal'))
                            ->placeholder(__('Geen vaste sporthal'))
                            ->options(fn () => Venue::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->helperText(__('Thuiswedstrijden opent waar mogelijk met deze sporthal.')),
                        Select::make('overlap_policy')
                            ->label(__('Bij een overlap'))
                            ->options([
                                OverlapPolicy::Deny->value => __('Blokkeren'),
                                OverlapPolicy::Warn->value => __('Waarschuwen en laten bevestigen'),
                                OverlapPolicy::Allow->value => __('Direct toestaan'),
                            ])
                            ->required(),
                        Toggle::make('show_trainings')
                            ->label(__('Trainingen tonen in de planner'))
                            ->helperText(__('Trainingen blijven wel meetellen bij conflictcontroles.'))
                            ->visible(fn (): bool => static::hasModule(PlannerFeature::Trainings)),
                        Toggle::make('publish_with_conflicts')
                            ->label(__('Publiceren met conflicten toestaan'))
                            ->helperText(__('Je krijgt nog steeds een waarschuwing voor publicatie.')),
                    ])->columns(2),
            ]);
    }

    protected function brandingTab(): Tab
    {
        return Tab::make(__('Club & huisstijl'))
            ->icon(Heroicon::OutlinedSparkles)
            ->schema([
                Section::make(__('Eigen teams herkennen'))
                    ->description(__('Wedstrijden van de Nevobo noemen teams voluit, bijvoorbeeld "VV Demo HS 1 - Orion HS 2". Aan het begin van de teamnaam herkent de planner welke teams van jouw club zijn.'))
                    ->icon(Heroicon::OutlinedIdentification)
                    ->schema([
                        TagsInput::make('team_name_patterns')
                            ->label(__('Teamnamen van je eigen club beginnen met…'))
                            ->placeholder(__('Bijvoorbeeld VV Demo'))
                            ->helperText(__('Voeg de clubnaam toe zoals die vooraan in de Nevobo-teamnamen staat, en eventueel een afkorting. Hoofdletters maken niet uit; het moet een heel woord zijn. Zonder invulling herkent de planner geen eigen teams.'))
                            ->nestedRecursiveRules(['string', 'max:100'])
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Huisstijl'))
                    ->description(__('Logo, kleur en teksten op het dagschema, de exports en de publieke pagina\'s.'))
                    ->icon(Heroicon::OutlinedSwatch)
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label(__('Logo'))
                            ->image()
                            ->disk('public')
                            ->directory(fn (): string => 'planner/'.app(CurrentClub::class)->id().'/branding')
                            ->visibility('public')
                            // No SVG: served from the app's own origin, an SVG
                            // can carry script that runs with a visitor's session.
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(2048)
                            ->helperText(__('PNG, JPG of WebP, maximaal 2 MB. Zonder logo blijft die plek leeg.')),
                        ColorPicker::make('primary_color')
                            ->label(__('Clubkleur'))
                            ->hex()
                            ->regex('/^#[0-9a-fA-F]{6}$/')
                            ->helperText(__('Accentkleur in de planner, op het dagschema en in de exports.')),
                        TextInput::make('schedule_tagline')
                            ->label(__('Slogan'))
                            ->maxLength(255)
                            ->placeholder(__('Bijvoorbeeld Samen sportief'))
                            ->helperText(__('Staat onder de clubnaam op het dagschema. Laat leeg om weg te laten.')),
                        Textarea::make('schedule_footer')
                            ->label(__('Voettekst dagschema'))
                            ->rows(2)
                            ->maxLength(255)
                            ->required()
                            ->helperText(__('Tekst die onderaan ieder geëxporteerd dagschema verschijnt.')),
                    ])->columns(2),
            ]);
    }

    protected function planningTab(): Tab
    {
        return Tab::make(__('Planning & periodes'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->schema([
                Section::make(__('Seizoen en competitie'))
                    ->description(__('Deze periodes gelden voor iedereen binnen de club.'))
                    ->icon(Heroicon::OutlinedCalendar)
                    ->schema([
                        DatePicker::make('season_start')->label(__('Start seizoen'))->native(false)->displayFormat('d-m-Y'),
                        DatePicker::make('season_end')->label(__('Einde seizoen'))->native(false)->displayFormat('d-m-Y')->afterOrEqual('season_start'),
                        DatePicker::make('competition_start')->label(__('Start competitie'))->native(false)->displayFormat('d-m-Y'),
                        DatePicker::make('competition_end')->label(__('Einde competitie'))->native(false)->displayFormat('d-m-Y')->afterOrEqual('competition_start'),
                    ])->columns(2),
                Section::make(__('Competitieregels voor verplaatsen'))
                    ->description(__('Houdt voorstellen van de planningsassistent binnen wat de bond toestaat. Een leeg veld betekent: geen regel. Datums gelden elk seizoen (dag-maand).'))
                    ->icon(Heroicon::OutlinedScale)
                    ->schema([
                        self::recurringDate('youth_spring_start', __('Jeugd: start voorjaarsreeks'))
                            ->helperText(__('Jeugdwedstrijden blijven in hun eigen seizoenshelft: een wedstrijd uit de najaarsreeks gaat niet naar het voorjaar en andersom. Bijvoorbeeld 01-01.')),
                        self::recurringDate('youth_spring_end', __('Jeugd: einde voorjaarsreeks'))
                            ->helperText(__('Laatste dag van de voorjaarsreeks voor de jeugd. Bijvoorbeeld 30-04.')),
                        self::recurringDate('internal_senior_deadline', __('Onderlinge seniorenwedstrijden vóór'))
                            ->helperText(__('Een wedstrijd tussen twee eigen seniorenteams moet voor deze dag gespeeld zijn. Bijvoorbeeld 01-03.')),
                    ])->columns(3),
                Section::make(__('Hoofdsporthal'))
                    ->description(__('De zaal waar de club normaal speelt. Staat er op een dag geen enkele thuiswedstrijd, dan toont de dagplanning deze zaal zodat je meteen kunt inplannen.'))
                    ->icon(Heroicon::OutlinedHome)
                    ->schema([
                        Select::make('main_venue_id')
                            ->label(__('Hoofdsporthal'))
                            ->options(fn (): array => Venue::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->native(false)
                            ->searchable()
                            ->placeholder(__('Geen voorkeur')),
                    ]),
                Section::make(__('Zaaldienst'))
                    ->description(__('Op dagen zonder zaaldienst verdwijnt de zaaldienstkolom uit de dagplanning.'))
                    ->icon(Heroicon::OutlinedKey)
                    ->visible(fn (): bool => static::hasModule(PlannerFeature::Duties))
                    ->schema([
                        CheckboxList::make('hall_duty_weekdays')
                            ->label(__('Zaaldienst op deze dagen'))
                            ->options([
                                1 => __('Maandag'), 2 => __('Dinsdag'), 3 => __('Woensdag'), 4 => __('Donderdag'),
                                5 => __('Vrijdag'), 6 => __('Zaterdag'), 0 => __('Zondag'),
                            ])
                            ->columns(4)
                            ->helperText(__('Al ingeplande zaaldiensten blijven altijd zichtbaar, ook op een uitgevinkte dag.')),
                        Repeater::make('hall_duty_exceptions')
                            ->label(__('Losse dagen zonder zaaldienst'))
                            ->helperText(__('Een toernooi of vrij weekend. Op deze dagen stelt de FTZ-planner geen zaaldienst voor, ook al is het een zaaldienstdag.'))
                            ->simple(
                                DatePicker::make('date')
                                    ->label(__('Datum'))
                                    ->native(false)
                                    ->displayFormat('d-m-Y')
                                    ->required(),
                            )
                            ->addActionLabel(__('Dag toevoegen'))
                            ->defaultItems(0)
                            ->reorderable(false),
                    ]),
                Section::make(__('Volgorde van teamgroepen'))
                    ->description(__('De volgorde waarin teamgroepen (heren, dames, jeugd) in overzichten staan.'))
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->schema([
                        Repeater::make('team_group_order')
                            ->label(__('Volgorde van de groepen'))
                            ->helperText(__('Sleep om de groepen te herschikken. Groepen die je hier weglaat komen achteraan.'))
                            ->simple(
                                Select::make('group')
                                    ->label(__('Groep'))
                                    ->options(collect(TeamCode::CATEGORY_ORDER)
                                        ->mapWithKeys(fn (string $group): array => [$group => TeamCode::categoryLabel($group)])
                                        ->all())
                                    ->native(false)
                                    ->required(),
                            )
                            ->addActionLabel(__('Groep toevoegen'))
                            ->defaultItems(0),
                    ]),
                Section::make(__('Trainingenperiode'))
                    ->description(__('Buiten deze periode worden trainingen niet bij Thuiswedstrijden getoond.'))
                    ->icon(Heroicon::OutlinedClock)
                    ->visible(fn (): bool => static::hasModule(PlannerFeature::Trainings))
                    ->schema([
                        DatePicker::make('training_period_start')->label(__('Trainingen vanaf'))->native(false)->displayFormat('d-m-Y'),
                        DatePicker::make('training_period_end')->label(__('Trainingen tot'))->native(false)->displayFormat('d-m-Y')->afterOrEqual('training_period_start'),
                    ])->columns(2),
            ]);
    }

    protected function dutiesTab(): Tab
    {
        return Tab::make(__('Taken & punten'))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->visible(fn (): bool => static::hasModule(PlannerFeature::Duties))
            ->schema([
                Section::make(__('Vrijwilligerspunten per taak'))
                    ->description(__('De FTZ-planner gebruikt deze waarden om geleverde en verbruikte inzet eerlijk te verdelen.'))
                    ->icon(Heroicon::OutlinedScale)
                    ->visible(fn (): bool => static::hasModule(PlannerFeature::VolunteerPoints))
                    ->schema([
                        TextInput::make('points_referee_duty')->label(__('Fluitdienst'))->numeric()->minValue(0)->suffix(__('punten'))->required(),
                        TextInput::make('points_scorer_duty')->label(__('Teldienst'))->numeric()->minValue(0)->suffix(__('punten'))->required(),
                        TextInput::make('points_hall_duty')->label(__('Zaaldienst'))->numeric()->minValue(0)->suffix(__('punten'))->required(),
                        TextInput::make('points_volleystars')->label(__('VolleyStars-taak'))->numeric()->minValue(0)->suffix(__('punten'))->required()
                            ->visible(fn (): bool => static::hasModule(PlannerFeature::Volleystars)),
                    ])->columns(2),
                Section::make(__('Wie mag welke taak'))
                    ->description(__('Teamcodes mogen als HS 1 of HS1 geschreven worden.'))
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->schema([
                        TagsInput::make('duty_rules.no_referee')
                            ->label(__('Teams die nooit fluiten'))
                            ->placeholder('DS 10')
                            ->helperText(__('Deze teams worden nooit als scheidsrechter voorgesteld.')),
                        TagsInput::make('duty_rules.lower_class_terms')
                            ->label(__('Woorden die een lagere klasse aanduiden'))
                            ->placeholder('3e')
                            ->helperText(__('A-jeugd en damesteams uit deze klassen mogen alleen jeugd of deze klassen fluiten.')),
                        Repeater::make('duty_rules.referee_requirements')
                            ->label(__('Wedstrijden met een vaste fluitploeg'))
                            ->addActionLabel(__('Regel toevoegen'))
                            ->schema([
                                TextInput::make('team')->label(__('Thuisspelend team'))->placeholder('HS 2')->required(),
                                TagsInput::make('allowed')->label(__('Mag gefloten worden door'))->placeholder('HS 1')->required(),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->defaultItems(0)
                            ->helperText(__('Staat een wedstrijd hier niet bij, dan mag ieder team fluiten dat verder aan de regels voldoet.')),
                        TagsInput::make('duty_rules.scorer_heavy_teams')
                            ->label(__('Wedstrijden waar C-jeugd niet mag tellen'))
                            ->placeholder('HS 1'),
                        TextInput::make('duty_rules.scorer_cutoff')
                            ->label(__('C-jeugd telt niet meer vanaf'))
                            ->placeholder('19:00')
                            ->rule('date_format:H:i')
                            ->required(),
                        TagsInput::make('duty_rules.hall_duty_codes')
                            ->label(__('Teamcodes die zaaldienst mogen doen'))
                            ->placeholder('DS')
                            ->helperText(__('Er wordt naar het begin van de teamcode gekeken: DS dekt DS 1 tot en met DS 10.'))
                            ->columnSpanFull(),
                        TextInput::make('duty_rules.max_duties_per_day')
                            ->label(__('Maximaal aantal taken per team per dag'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(20)
                            ->required()
                            ->helperText(__('Fluiten, tellen en zaaldienst bij elkaar. 0 betekent geen grens; de generator laat een taak dan liever leeg dan dat hij een team een hele dag achter elkaar inzet.')),
                    ])->columns(2),
            ]);
    }

    protected function requestsTab(): Tab
    {
        return Tab::make(__('Wijzigingsverzoeken'))
            ->icon(Heroicon::OutlinedInboxArrowDown)
            ->visible(fn (): bool => static::hasModule(PlannerFeature::ChangeRequests))
            ->schema([
                Section::make(__('De links voor contactpersonen'))
                    ->description(__('Deel de juiste link met de contactpersonen van de teams. Ze blijven het hele seizoen gelijk.'))
                    ->icon(Heroicon::OutlinedLink)
                    ->schema([
                        TextInput::make('request_link')
                            ->label(__('Wedstrijd wijzigen'))
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        TextInput::make('duty_request_link')
                            ->label(__('FTZ-dienst wijzigen'))
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (): bool => static::hasModule(PlannerFeature::Duties))
                            ->columnSpanFull(),
                        Toggle::make('requests_open')
                            ->label(__('Formulieren nemen verzoeken aan'))
                            ->helperText(__('Staat dit uit, dan ziet een contactpersoon dat het formulier gesloten is; de links blijven bestaan en leggen dat uit.'))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Redenen in de formulieren'))
                    ->description(__('Wedstrijd- en FTZ-verzoeken hebben ieder een passende lijst. "Overig" staat er altijd bij en vraagt om een eigen omschrijving.'))
                    ->icon(Heroicon::OutlinedListBullet)
                    ->schema([
                        TagsInput::make('request_reasons')
                            ->label(__('Redenen voor een wedstrijdwijziging'))
                            ->placeholder(__('Reden toevoegen'))
                            ->helperText(__('Laat leeg om de standaardlijst te gebruiken.'))
                            ->columnSpanFull(),
                        TagsInput::make('duty_request_reasons')
                            ->label(__('Redenen voor een FTZ-wijziging'))
                            ->placeholder(__('Reden toevoegen'))
                            ->helperText(__('Laat leeg om de aparte FTZ-standaardlijst te gebruiken.'))
                            ->visible(fn (): bool => static::hasModule(PlannerFeature::Duties))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** Where the planning shows up outside the planner: pages, embeds, feeds. */
    protected function websiteTab(): Tab
    {
        $club = app(CurrentClub::class)->get();
        $link = fn (string $route): string => $club ? route($route, ['club' => $club]) : '';

        return Tab::make(__('Website & feeds'))
            ->icon(Heroicon::OutlinedGlobeAlt)
            ->schema([
                Section::make(__('Publieke pagina\'s'))
                    ->description(__('Deel deze links of zet ze op de clubwebsite. Met ?embed=1 erachter passen het overzicht en de verzoekformulieren in een iframe.'))
                    ->icon(Heroicon::OutlinedLink)
                    ->schema([
                        TextInput::make('public_home_link')->label(__('Startpagina'))->default($link('planner.public.home'))->disabled()->dehydrated(false)->copyable(),
                        TextInput::make('public_matches_link')->label(__('Wedstrijdoverzicht'))->default($link('planner.public.matches'))->disabled()->dehydrated(false)->copyable(),
                    ]),
                Section::make(__('RSS-feeds'))
                    ->description(__('Voor de clubwebsite, een app of een agenda: de feeds volgen de actieve planning.'))
                    ->icon(Heroicon::OutlinedRss)
                    ->schema(static::hasModule(PlannerFeature::RssFeeds)
                        ? [
                            TextInput::make('rss_central_link')->label(__('Alle wedstrijden en diensten'))->default($link('planner.public.rss.central'))->disabled()->dehydrated(false)->copyable(),
                            TextInput::make('rss_home_link')->label(__('Thuiswedstrijden'))->default($link('planner.public.rss.home'))->disabled()->dehydrated(false)->copyable(),
                        ]
                        : LockedFeature::notice(PlannerFeature::RssFeeds)),
                Section::make(__('Zitvolleybal'))
                    ->description(__('Het zitvolleybalteam (XZ 1) speelt in een eigen zaal: wedstrijden daar worden thuiswedstrijden met de zitvolleybalindeling, elders uitwedstrijden.'))
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->schema(static::hasModule(PlannerFeature::SittingVolleyball)
                        ? [Text::make(__('Staat aan: bij het importeren herkent de planner de zitvolleybalwedstrijden vanzelf.'))]
                        : LockedFeature::notice(PlannerFeature::SittingVolleyball)),
            ]);
    }

    protected function colorsTab(): Tab
    {
        return Tab::make(__('Kleuren'))
            ->icon(Heroicon::OutlinedSwatch)
            ->visible(fn (): bool => auth()->user()?->can('ViewAny:ActivityColor') ?? false)
            ->schema([
                Section::make(__('Plannerkleuren per activiteitstype'))
                    ->description(__('Activiteiten uit de kalenderimport krijgen de kleur van hun type in de planner en kalender.'))
                    ->icon(Heroicon::OutlinedSwatch)
                    ->schema([
                        Repeater::make('activity_colors')
                            ->label(__('Kleuren'))
                            ->addActionLabel(__('Kleur toevoegen'))
                            ->reorderable(false)
                            ->defaultItems(0)
                            ->columns(12)
                            ->itemLabel(fn (array $state): ?string => $state['type'] ?? null)
                            ->schema([
                                TextInput::make('type')
                                    ->label(__('Activiteitstype'))
                                    ->placeholder(__('Bijv. Feestdag of Bekerronde'))
                                    ->required()
                                    ->maxLength(100)
                                    ->distinct()
                                    ->helperText(__('Gebruik exact dezelfde schrijfwijze als in de kalenderimport.'))
                                    ->columnSpan(['default' => 12, 'md' => 8]),
                                ColorPicker::make('color')
                                    ->label(__('Plannerkleur'))
                                    ->required()
                                    ->columnSpan(['default' => 12, 'md' => 4]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function technicalTab(): Tab
    {
        return Tab::make(__('Technisch'))
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->schema([
                Section::make(__('Conflictcontrole en externe bronnen'))
                    ->description(__('Technische marges voor controles en gegevensverversing.'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->schema([
                        TextInput::make('buffer_minutes')
                            ->label(__('Buffermarge'))
                            ->numeric()->minValue(0)->maxValue(120)->suffix(__('minuten'))->required()
                            ->helperText(__('Extra tijd vóór en na een activiteit bij overlapcontroles.')),
                        TextInput::make('cache_ttl_minutes')
                            ->label(__('RSS-cache bewaren'))
                            ->numeric()->minValue(1)->maxValue(1440)->suffix(__('minuten'))->required(),
                        Toggle::make('admin_override_external_error')
                            ->label(__('Doorgaan als een externe bron faalt'))
                            ->helperText(__('Beheerders kunnen verder werken wanneer een externe beschikbaarheidsbron tijdelijk niet reageert.'))
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    /**
     * A day-month field stored as "MM-DD", shown and typed as "DD-MM".
     */
    private static function recurringDate(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->placeholder(__('dd-mm'))
            ->maxLength(5)
            ->regex('/^(0[1-9]|[12]\d|3[01])-(0[1-9]|1[0-2])$/')
            ->validationMessages(['regex' => __('Gebruik dag-maand, bijvoorbeeld 01-03.')])
            ->formatStateUsing(fn (?string $state): ?string => $state ? implode('-', array_reverse(explode('-', $state))) : null)
            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? implode('-', array_reverse(explode('-', $state))) : null);
    }
}
