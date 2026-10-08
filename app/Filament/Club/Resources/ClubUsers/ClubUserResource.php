<?php

namespace App\Filament\Club\Resources\ClubUsers;

use App\Filament\Club\Resources\ClubUsers\Pages\ListClubUsers;
use App\Models\Club;
use App\Models\User;
use App\Planner\Features\PlanLimit;
use App\Planner\Features\PlanLimits;
use App\Planner\Support\ClubRole;
use App\Support\Tenancy\ClubInvitations;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
                    ->hidden(fn (): bool => PlanLimits::reached(PlanLimit::Admins))
                    ->schema([
                        TextInput::make('name')->label('Naam')->required()->maxLength(255),
                        TextInput::make('email')->label('E-mail')->email()->required()->maxLength(255),
                        Select::make('role')->label('Rol')->options(ClubRole::class)->required()
                            ->default(ClubRole::PlanningCommittee->value),
                    ])
                    ->action(function (array $data): void {
                        app(ClubInvitations::class)->invite(self::club(), $data['name'], $data['email'], self::role($data['role']));

                        Notification::make()->title('Uitnodiging verstuurd')->success()->send();
                    }),
                PlanLimits::lockedAction(PlanLimit::Admins, 'inviteLocked', 'Uitnodigen'),
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
