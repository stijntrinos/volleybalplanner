<?php

namespace App\Filament\Platform\Resources\Clubs\RelationManagers;

use App\Models\Club;
use App\Models\User;
use App\Planner\Support\ClubRole;
use App\Support\Tenancy\ClubInvitations;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use STS\FilamentImpersonate\Actions\Impersonate;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Gebruikers';

    public function table(Table $table): Table
    {
        /** @var Club $club */
        $club = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable(),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('club_role')
                    ->label('Rol')
                    ->state(fn (User $record): ?string => $record->clubRole($club)?->getLabel()),
            ])
            ->headerActions([
                Action::make('inviteAdmin')
                    ->label('Beheerder uitnodigen')
                    ->icon('heroicon-o-envelope')
                    ->schema([
                        TextInput::make('name')->label('Naam')->required()->maxLength(255),
                        TextInput::make('email')->label('E-mail')->email()->required()->maxLength(255),
                    ])
                    ->action(function (array $data) use ($club): void {
                        app(ClubInvitations::class)->invite($club, $data['name'], $data['email'], ClubRole::ClubAdmin);

                        Notification::make()->title('Uitnodiging verstuurd')->success()->send();
                    }),
            ])
            ->recordActions([
                Impersonate::make()->redirectTo(fn (): string => url("/app/{$club->slug}")),
                Action::make('removeFromClub')
                    ->label('Verwijderen uit club')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (User $record) use ($club): void {
                        $record->removeClubRoles($club);
                        $club->users()->detach($record);
                    }),
            ]);
    }
}
