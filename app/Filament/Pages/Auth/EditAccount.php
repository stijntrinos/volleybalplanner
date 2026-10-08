<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * "Mijn account": name, e-mail address, password, passkeys and two-factor
 * authentication, as on the Aetos site. Registered on both panels and
 * reached through the account menu.
 */
class EditAccount extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Mijn account';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static bool $shouldRegisterNavigation = false;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament-panels::pages.page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getSlug(?Panel $panel = null): string
    {
        return 'account';
    }

    public function mount(): void
    {
        $this->form->fill($this->getUser()->only(['name', 'email']));
    }

    public function getUser(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profiel')
                    ->aside()
                    ->description('Je naam en het e-mailadres waarmee je inlogt en waarop je meldingen ontvangt.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('E-mailadres')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true),
                    ]),
                Section::make('Wachtwoord')
                    ->aside()
                    ->description('Kies een sterk wachtwoord van minimaal 8 tekens. Laat leeg om het te houden.')
                    ->schema([
                        TextInput::make('password')
                            ->label('Nieuw wachtwoord')
                            ->password()
                            ->revealable()
                            ->rule(Password::default())
                            ->autocomplete('new-password')
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->dehydrateStateUsing(fn ($state): string => Hash::make($state))
                            ->live(debounce: 500)
                            ->same('passwordConfirmation'),
                        TextInput::make('passwordConfirmation')
                            ->label('Bevestig nieuw wachtwoord')
                            ->password()
                            ->revealable()
                            ->required()
                            ->visible(fn (Get $get): bool => filled($get('password')))
                            ->dehydrated(false),
                        TextInput::make('currentPassword')
                            ->label('Huidig wachtwoord')
                            ->password()
                            ->revealable()
                            ->autocomplete('current-password')
                            ->currentPassword(guard: Filament::getAuthGuard())
                            ->required()
                            // Also for a new e-mail address: with a taken-over
                            // session it would otherwise lead to a password reset
                            // on the attacker's address.
                            ->helperText(fn (Get $get): ?string => $this->emailChanged($get) ? 'Nodig om je e-mailadres te wijzigen.' : null)
                            ->visible(fn (Get $get): bool => filled($get('password')) || $this->emailChanged($get))
                            ->dehydrated(false),
                    ]),
            ])
            ->model($this->getUser())
            ->operation('edit')
            ->statePath('data');
    }

    private function emailChanged(Get $get): bool
    {
        return mb_strtolower(trim((string) $get('email'))) !== mb_strtolower((string) $this->getUser()->email);
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            $this->getUser()->update($data);
        } catch (Halt) {
            return;
        }

        if (request()->hasSession() && array_key_exists('password', $data)) {
            request()->session()->put([
                'password_hash_'.Filament::getAuthGuard() => $data['password'],
            ]);
        }

        $this->data['password'] = null;
        $this->data['passwordConfirmation'] = null;
        $this->data['currentPassword'] = null;

        Notification::make()
            ->success()
            ->title('Opgeslagen')
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        $components = [
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make($this->getFormActions())
                        ->alignment(Alignment::Start)
                        ->key('form-actions'),
                ]),
            Section::make('Passkeys')
                ->aside()
                ->description('Log in met je vingerafdruk, gezicht of apparaatpincode in plaats van je wachtwoord. Een passkey is gekoppeld aan dit apparaat of je wachtwoordbeheerder.')
                ->schema([
                    Livewire::make('filament-passkeys'),
                ]),
        ];

        if (Filament::hasMultiFactorAuthentication()) {
            $components[] = Section::make('Tweestapsverificatie (2FA)')
                ->aside()
                ->description('Vraag bij het inloggen ook om een code uit een authenticator-app, zoals Google Authenticator, 1Password of Microsoft Authenticator.')
                ->compact()
                ->divided()
                ->schema(
                    collect(Filament::getMultiFactorAuthenticationProviders())
                        ->map(fn ($provider): Component => Group::make($provider->getManagementSchemaComponents())
                            ->statePath($provider->getId()))
                        ->all(),
                );
        }

        return $schema->components($components);
    }

    /** @return list<Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Opslaan')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }
}
