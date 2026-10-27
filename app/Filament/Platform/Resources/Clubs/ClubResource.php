<?php

namespace App\Filament\Platform\Resources\Clubs;

use App\Filament\Platform\Resources\Clubs\Pages\CreateClub;
use App\Filament\Platform\Resources\Clubs\Pages\EditClub;
use App\Filament\Platform\Resources\Clubs\Pages\ListClubs;
use App\Filament\Platform\Resources\Clubs\RelationManagers\UsersRelationManager;
use App\Filament\Platform\Resources\Clubs\Schemas\ClubForm;
use App\Filament\Platform\Resources\Clubs\Tables\ClubsTable;
use App\Models\Club;
use App\Models\User;
use App\Planner\Exceptions\NevoboUnavailable;
use App\Planner\Filament\Support\NevoboLinkForm;
use App\Planner\Services\Nevobo\NevoboClubDirectory;
use App\Support\Demo\DemoClubs;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClubResource extends Resource
{
    protected static ?string $model = Club::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'club';

    protected static ?string $pluralModelLabel = 'clubs';

    public static function form(Schema $schema): Schema
    {
        return ClubForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClubsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubs::route('/'),
            'create' => CreateClub::route('/create'),
            'edit' => EditClub::route('/{record}/edit'),
        ];
    }

    /**
     * Opens the club's workspace; platform admins can enter every club.
     */
    public static function openClubAction(): Action
    {
        return Action::make('openClub')
            ->label('Naar club')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->url(fn (Club $record): string => Filament::getPanel('club')->getUrl($record))
            ->openUrlInNewTab();
    }

    /**
     * Makes a demo club without a demo request (DemoClubs::create): filled
     * with the example planning, or with the teams and programme of a
     * Nevobo club so a prospect sees their own season. The contact is
     * optional; a platform admin can enter every club anyway.
     */
    public static function createDemoAction(): Action
    {
        return Action::make('createDemo')
            ->label(__('Demo aanmaken'))
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->modalHeading(__('Demo aanmaken'))
            ->modalDescription(__(
                'De democlub komt op het grootste pakket met een proefperiode van :days dagen. Daarna staat hij in leesstand; :prune dagen later wordt hij verwijderd, tenzij je hem omzet naar klant.',
                ['days' => (int) config('planner.demo.trial_days', 14), 'prune' => (int) config('planner.demo.prune_after_days', 30)],
            ))
            ->modalSubmitActionLabel(__('Demo aanmaken'))
            ->modalWidth(Width::FiveExtraLarge)
            ->schema([
                Radio::make('source')
                    ->label(__('Vullen met'))
                    ->options([
                        'example' => __('Voorbeelddata'),
                        'nevobo' => __('Teams en programma van een vereniging bij de Nevobo'),
                    ])
                    ->descriptions([
                        'example' => __('VC Voorbeeld: 24 teams, twee sporthallen, een gepubliceerde versie, een concept met wijzigingen en open verzoeken.'),
                        'nevobo' => __('De gekozen teams met hun Nevobo-feeds, de sporthallen van hun thuiswedstrijden en een conceptversie met het programma.'),
                    ])
                    ->default('example')
                    ->required()
                    ->live(),
                Group::make(NevoboLinkForm::components(static fn (): ?Club => null))
                    ->visible(static fn (Get $get): bool => $get('source') === 'nevobo'),
                TextInput::make('name')
                    ->label(__('Naam van de vereniging'))
                    ->helperText(static fn (Get $get): string => $get('source') === 'nevobo'
                        ? __('Leeg = de naam bij de Nevobo. De club heet ":name (demo)".', ['name' => __('naam')])
                        : __('De club heet ":name (demo)".', ['name' => __('naam')]))
                    ->required(static fn (Get $get): bool => $get('source') !== 'nevobo')
                    ->maxLength(100),
                Grid::make(2)->schema([
                    TextInput::make('contact_name')
                        ->label(__('Naam contactpersoon'))
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label(__('E-mailadres contactpersoon'))
                        ->helperText(__('Optioneel: krijgt een uitnodiging als clubbeheerder.'))
                        ->email()
                        ->maxLength(255),
                ]),
            ])
            ->action(function (array $data, Action $action): void {
                $nevoboCode = $data['source'] === 'nevobo' ? (string) $data['club_code'] : null;
                $teamIds = array_values(array_map('strval', (array) ($data['team_ids'] ?? [])));

                if ($nevoboCode !== null && $teamIds === []) {
                    Notification::make()->title(__('Kies minstens één team om te koppelen'))->warning()->send();
                    $action->halt();
                }

                $name = trim((string) ($data['name'] ?? ''));
                if ($name === '' && $nevoboCode !== null) {
                    $name = rescue(static fn (): string => (string) (app(NevoboClubDirectory::class)->club($nevoboCode)['name'] ?? ''), '', report: false);
                }

                $user = auth()->user();

                try {
                    ['club' => $club, 'nevobo' => $nevobo] = app(DemoClubs::class)->create(
                        $name !== '' ? $name : $nevoboCode ?? __('Demo'),
                        $data['contact_name'] ?? null,
                        $data['email'] ?? null,
                        $nevoboCode,
                        $teamIds,
                        $user instanceof User ? $user : null,
                    );
                } catch (NevoboUnavailable $exception) {
                    Notification::make()->title(__('Nevobo niet bereikbaar'))->body($exception->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }

                $notification = Notification::make()->title(__('Demo aangemaakt'))->actions([
                    Action::make('open')->label(__('Naar demo'))->url(Filament::getPanel('club')->getUrl($club), shouldOpenInNewTab: true),
                ]);

                if ($nevobo === null) {
                    $notification->body(__(':club staat klaar met voorbeelddata.', ['club' => $club->name]))->success();
                } elseif ($nevobo['error'] !== null) {
                    $notification->body(__(':club heeft :teams teams, maar het programma kon niet worden geïmporteerd: :error', ['club' => $club->name, 'teams' => $nevobo['teams'], 'error' => $nevobo['error']]))->warning();
                } else {
                    $notification->body(__(':club staat klaar: :teams teams, :venues sporthallen en :matches wedstrijden in de conceptversie.', ['club' => $club->name] + $nevobo))->success();
                }

                if (filled($data['email'] ?? null)) {
                    $notification->body($notification->getBody().' '.__('De uitnodiging is verstuurd naar :email.', ['email' => $data['email']]));
                }

                $notification->send();
            });
    }

    /**
     * A demo that became a customer: it is no longer pruned. Name, slug,
     * plan and subscription are then edited as for any club.
     */
    public static function convertDemoAction(): Action
    {
        return Action::make('convertDemo')
            ->label(__('Omzetten naar klant'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Club $record): bool => $record->is_demo)
            ->requiresConfirmation()
            ->modalDescription(__('De club is dan geen demo meer en wordt niet automatisch verwijderd. Pas daarna naam, slug, pakket en abonnement aan.'))
            ->action(function (Club $record): void {
                $record->update(['is_demo' => false]);
                Notification::make()->title(__('Omgezet naar klant'))->success()->send();
            });
    }

    /**
     * Sets up a new customer's teams and Nevobo feeds in one go; the same
     * flow as the club's own "Nevobo-koppeling" page.
     */
    public static function linkNevoboAction(): Action
    {
        return Action::make('linkNevobo')
            ->label(__('Koppel aan Nevobo'))
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->modalHeading(fn (Club $record): string => __('Koppel :club aan de Nevobo', ['club' => $record->name]))
            ->modalDescription(__('Zoek de vereniging, kies de teams en de planner maakt teams en feeds aan in deze club.'))
            ->modalSubmitActionLabel(__('Koppelen'))
            ->modalWidth(Width::FiveExtraLarge)
            ->fillForm(fn (Club $record): array => NevoboLinkForm::initialState($record))
            ->schema(fn (Club $record): array => NevoboLinkForm::components(fn (): Club => $record))
            ->action(function (Club $record, array $data, Action $action): void {
                if (NevoboLinkForm::submit($record, $data) === null) {
                    $action->halt();
                }
            });
    }
}
