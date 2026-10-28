<?php

namespace App\Filament\Platform\Resources\Clubs\RelationManagers;

use App\Models\Club;
use App\Models\User;
use App\Planner\Support\ClubRole;
use App\Support\Tenancy\ClubInvitations;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
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
                $this->createUserAction($club),
                Action::make('inviteAdmin')
                    ->label('Beheerder uitnodigen')
                    ->icon('heroicon-o-envelope')
                    ->schema([
                        TextInput::make('name')->label('Naam')->required()->maxLength(255),
                        TextInput::make('email')->label('E-mail')->email()->required()->maxLength(255),
                    ])
                    ->action(function (array $data) use ($club): void {
                        $invitation = app(ClubInvitations::class)->invite($club, $data['name'], $data['email'], ClubRole::ClubAdmin, auth()->user());

                        Notification::make()->title($invitation ? 'Uitnodiging verstuurd' : 'Rol bijgewerkt')->success()->send();
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

    /**
     * Adds a user with a password the platform admin sets, for when an
     * invitation mail is no option. An existing account only joins the club
     * with the role: its password belongs to its owner and is left alone.
     */
    private function createUserAction(Club $club): Action
    {
        return Action::make('createUser')
            ->label('Gebruiker aanmaken')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('gray')
            ->modalDescription('De gebruiker kan meteen inloggen, zonder e-mail. Bestaat het e-mailadres al, dan wordt dat account alleen aan de club toegevoegd en blijft het wachtwoord ongewijzigd.')
            ->schema([
                TextInput::make('name')->label('Naam')->required()->maxLength(255),
                TextInput::make('email')->label('E-mail')->email()->required()->maxLength(255),
                Select::make('role')
                    ->label('Rol')
                    ->options(ClubRole::class)
                    ->default(ClubRole::ClubAdmin->value)
                    ->required(),
                TextInput::make('password')
                    ->label('Wachtwoord')
                    ->password()
                    ->revealable()
                    ->rule(Password::default())
                    ->autocomplete('new-password')
                    ->helperText('Alleen voor een nieuw account. Geef het wachtwoord zelf door aan de gebruiker.')
                    ->required(fn (Get $get): bool => !User::query()->where('email', Str::lower(trim((string) $get('email'))))->exists())
                    ->hintAction(
                        Action::make('generatePassword')
                            ->label('Genereren')
                            ->icon(Heroicon::OutlinedSparkles)
                            ->action(fn (Set $set) => $set('password', Str::password(16, symbols: false))),
                    ),
            ])
            ->action(function (array $data) use ($club): void {
                $email = Str::lower(trim($data['email']));
                $role = $data['role'] instanceof ClubRole ? $data['role'] : ClubRole::from($data['role']);

                $created = DB::transaction(function () use ($club, $data, $email, $role): bool {
                    $user = User::query()->where('email', $email)->first();
                    $created = $user === null;

                    if ($created) {
                        $user = User::create(['name' => trim($data['name']), 'email' => $email, 'password' => $data['password']]);
                        // The platform admin vouches for the address.
                        $user->forceFill(['email_verified_at' => now()])->save();
                    }

                    $club->users()->syncWithoutDetaching([$user->id]);
                    $user->assignClubRole($club, $role);

                    return $created;
                });

                Notification::make()
                    ->title($created ? 'Gebruiker aangemaakt' : 'Bestaand account toegevoegd aan de club')
                    ->body($created ? null : 'Het wachtwoord van dit account is niet gewijzigd.')
                    ->success()
                    ->send();
            });
    }
}
