<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditAccount;
use App\Filament\Platform\Widgets\ClubsOverview;
use App\Http\Middleware\RequireStrongAuthentication;
use App\Planner\Support\DesignTokens;
use App\Planner\Support\InitialsAvatarProvider;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use MarcelWeidum\Passkeys\PasskeysPlugin;

/**
 * Platform administration: clubs, their modules and their first admins.
 * Only for users with is_platform_admin.
 */
class PlatformPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Without built assets (a fresh checkout running the tests) the panel
        // keeps Filament's default theme instead of failing on the manifest.
        if (is_file(public_path('hot')) || is_file(public_path('build/manifest.json'))) {
            $panel->viteTheme('resources/css/planner.css');
        }

        return $panel
            ->id('platform')
            ->topbar(false)
            ->path('admin')
            ->login()
            // Login and panel pages stay out of search engines.
            ->renderHook(PanelsRenderHook::HEAD_START, fn (): string => '<meta name="robots" content="noindex, nofollow">')
            ->passwordReset()
            // Account security as on the Aetos site: an authenticator app as
            // second step, passkeys instead of a password, "Mijn account".
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable()->brandName('Volleybalplanner Platform'),
            ], isRequired: true)
            // "Required" only for who must: RequireStrongAuthentication decides per user.
            ->multiFactorAuthenticationRequiredMiddlewareName(RequireStrongAuthentication::class)
            ->plugin(PasskeysPlugin::make())
            ->pages([EditAccount::class])
            ->userMenuItems([
                'profile' => fn (Action $action): Action => $action
                    ->label('Mijn account')
                    ->icon('heroicon-m-user-circle')
                    ->url(fn (): string => EditAccount::getUrl()),
            ])
            ->brandName('Volleybalplanner Platform')
            // Same design system as the club workspace: the panel theme, Outfit,
            // the planner palettes and the in-house initials avatars.
            ->font(DesignTokens::FONT)
            ->colors(DesignTokens::palettes())
            ->brandLogo(fn (): View => view('planner::filament.logo'))
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->discoverResources(in: app_path('Filament/Platform/Resources'), for: 'App\\Filament\\Platform\\Resources')
            ->discoverPages(in: app_path('Filament/Platform/Pages'), for: 'App\\Filament\\Platform\\Pages')
            ->pages([Dashboard::class])
            ->widgets([ClubsOverview::class])
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
