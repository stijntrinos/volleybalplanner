<?php

namespace App\Filament\Club\Resources\ClubUsers;

use App\Filament\Club\Resources\ClubUsers\Pages\ListClubUsers;
use App\Models\Club;
use App\Models\User;
use App\Planner\Features\PlanLimit;
use App\Planner\Features\PlanLimits;
use App\Planner\Support\ClubRole;
use App\Planner\Support\CommitteeRole;
use App\Planner\Support\PlannerPermissions;
use App\Support\Tenancy\ClubInvitations;
use App\Support\Tenancy\ReadOnlyClub;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lets a club admin manage who has access to their club. Users can belong to
 * several clubs, so this lists members of the current club only and never
 * edits the user account itself.
 */
class ClubUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $slug = 'club-users';

    protected static ?string $modelLabel = 'gebruiker';

    protected static ?string $pluralModelLabel = 'gebruikers';

    protected static ?string $navigationLabel = 'Gebruikers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 90;

    public static function canAccess(): bool
    {
        $club = Filament::getTenant();
        $user = auth()->user();

        return $club instanceof Club
            && $user instanceof User
            && ($user->isPlatformAdmin() || $user->hasClubRole($club, ClubRole::ClubAdmin));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('clubs', fn (Builder $query) => $query->whereKey(Filament::getTenant()?->getKey()));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable()->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('club_role')
                    ->label('Rol')
                    ->state(fn (User $record): ?string => $record->clubRole(self::club())?->getLabel()),
            ])
            ->defaultSort('name')
            ->headerActions([
                Action::make('invite')
                    ->label('Uitnodigen')
                    ->icon('heroicon-o-envelope')
                    ->hidden(fn (): bool => ReadOnlyClub::active() || PlanLimits::reached(PlanLimit::Admins))
                    ->schema([
                        TextInput::make('name')->label('Naam')->required()->maxLength(255),
                        TextInput::make('email')->label('E-mail')->email()->required()->maxLength(255),
                        Select::make('role')->label('Rol')->options(ClubRole::class)->required()
                            ->default(ClubRole::PlanningCommittee->value),
                    ])
                    ->action(function (array $data): void {
                        $invitation = app(ClubInvitations::class)->invite(self::club(), $data['name'], $data['email'], self::role($data['role']), auth()->user());

                        Notification::make()
                            ->title($invitation ? 'Uitnodiging verstuurd' : 'Rol bijgewerkt')
                            ->body($invitation ? 'Na acceptatie staat '.$invitation->name.' in de lijst.' : 'Deze persoon was al lid van de club.')
                            ->success()
                            ->send();
                    }),
                PlanLimits::lockedAction(PlanLimit::Admins, 'inviteLocked', 'Uitnodigen')
                    ->hidden(fn (): bool => ReadOnlyClub::active()),
                Action::make('invitations')
                    ->label(fn (): string => 'Openstaand ('.self::club()->invitations()->pending()->count().')')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->visible(fn (): bool => self::club()->invitations()->pending()->exists())
                    ->modalHeading('Openstaande uitnodigingen')
                    ->modalDescription('Deze mensen zijn uitgenodigd maar hebben nog niet geaccepteerd. Ze tellen mee voor het aantal beheerders van je pakket.')
                    ->modalContent(fn (): View => view('filament.club.pending-invitations', [
                        'invitations' => self::club()->invitations()->pending()->latest()->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Sluiten'),
                Action::make('security')
                    ->label('Beveiliging')
                    ->icon('heroicon-o-finger-print')
                    ->color('gray')
                    ->modalHeading('Beveiliging van accounts')
                    ->fillForm(fn (): array => ['require_strong_auth' => self::club()->require_strong_auth])
                    ->schema([
                        Toggle::make('require_strong_auth')
                            ->label('Passkey of tweestapsverificatie verplicht')
                            ->helperText('Wie geen passkey of authenticator-app heeft, moet die bij de volgende keer inloggen eerst instellen. Geldt voor iedereen in deze club.'),
                    ])
                    ->modalSubmitActionLabel('Opslaan')
                    ->action(function (array $data): void {
                        self::club()->update(['require_strong_auth' => (bool) $data['require_strong_auth']]);

                        Notification::make()->title('Opgeslagen')->success()->send();
                    }),
                Action::make('committeePermissions')
                    ->label('Rechten '.mb_strtolower(ClubRole::PlanningCommittee->getLabel()))
                    ->hidden(fn (): bool => ReadOnlyClub::active())
                    ->icon('heroicon-o-shield-check')
                    ->color('gray')
                    ->modalHeading('Wat mag de '.mb_strtolower(ClubRole::PlanningCommittee->getLabel()).'?')
                    ->modalDescription('Clubbeheerders mogen altijd alles. Zet hier aan wat leden van de '.mb_strtolower(ClubRole::PlanningCommittee->getLabel()).' mogen zien en doen.')
                    ->fillForm(fn (): array => ['groups' => CommitteeRole::groups(self::club())])
                    ->schema([
                        CheckboxList::make('groups')
                            ->hiddenLabel()
                            ->options(collect(PlannerPermissions::groups())->mapWithKeys(fn (string $group): array => [$group => __('planner.permission_groups.'.$group)])->all())
                            ->descriptions(collect(PlannerPermissions::groups())->mapWithKeys(fn (string $group): array => [$group => __('planner.permission_group_descriptions.'.$group)])->all())
                            ->bulkToggleable(),
                    ])
                    ->modalSubmitActionLabel('Opslaan')
                    ->action(function (array $data): void {
                        CommitteeRole::update(self::club(), array_values((array) ($data['groups'] ?? [])));

                        Notification::make()->title('Rechten opgeslagen')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('changeRole')
                    ->label('Rol wijzigen')
                    ->icon('heroicon-o-key')
                    ->hidden(fn (User $record): bool => $record->is(auth()->user()))
                    ->fillForm(fn (User $record): array => ['role' => $record->clubRole(self::club())?->value])
                    ->schema([
                        Select::make('role')->label('Rol')->options(ClubRole::class)->required(),
                    ])
                    ->action(fn (User $record, array $data) => $record->assignClubRole(self::club(), self::role($data['role']))),
                Action::make('remove')
                    ->label('Verwijderen uit club')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->hidden(fn (User $record): bool => $record->is(auth()->user()))
                    ->action(function (User $record): void {
                        $record->removeClubRoles(self::club());
                        self::club()->users()->detach($record);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubUsers::route('/'),
        ];
    }

    private static function club(): Club
    {
        $club = Filament::getTenant();
        assert($club instanceof Club);

        return $club;
    }

    private static function role(ClubRole|string $role): ClubRole
    {
        return $role instanceof ClubRole ? $role : ClubRole::from($role);
    }
}
