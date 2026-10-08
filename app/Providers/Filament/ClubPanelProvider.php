<?php

namespace App\Providers\Filament;

use App\Filament\Club\Pages\Subscription;
use App\Filament\Pages\Auth\EditAccount;
use App\Http\Middleware\RequireStrongAuthentication;
use App\Models\Club;
use App\Planner\PlannerPlugin;
use BezhanSalleh\FilamentShield\Middleware\SyncShieldTenant;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use MarcelWeidum\Passkeys\PasskeysPlugin;

/**
 * The club workspace: one tenant per club, holding the complete planner.
 */
class ClubPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('club')
            ->topbar(false)
            ->path('app')
            ->login()
            // Login and panel pages stay out of search engines.
            ->renderHook(PanelsRenderHook::HEAD_START, fn (): string => '<meta name="robots" content="noindex, nofollow">')
            ->passwordReset()
            // Account security as on the Aetos site: an authenticator app as
            // second step, passkeys instead of a password, "Mijn account".
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable()->brandName('Volleybalplanner'),
            ], isRequired: true)
            // "Required" only for who must: RequireStrongAuthentication decides per user.
            ->multiFactorAuthenticationRequiredMiddlewareName(RequireStrongAuthentication::class)
            ->plugin(PasskeysPlugin::make())
            ->pages([EditAccount::class, Subscription::class])
            ->userMenuItems([
                'profile' => fn (Action $action): Action => $action
                    ->label('Mijn account')
                    ->icon('heroicon-m-user-circle')
                    ->url(fn (): string => EditAccount::getUrl()),
            ])
            ->brandName('Volleybalplanner')
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->tenant(Club::class, slugAttribute: 'slug', ownershipRelationship: 'club')
            ->tenantMiddleware([
                SyncShieldTenant::class,
            ], isPersistent: true)
            ->discoverResources(in: app_path('Filament/Club/Resources'), for: 'App\\Filament\\Club\\Resources')
            ->plugin(PlannerPlugin::make())
            // Trial or subscription about to end: a notice for club admins.
            ->renderHook(PanelsRenderHook::CONTENT_START, fn (): View => view('filament.club.subscription-banner'))
            // The upgrade pop-up behind locked modules and reached limits.
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => Filament::getTenant() ? Blade::render('@livewire(\App\Livewire\PlanUpgradePrompt::class)') : '')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
