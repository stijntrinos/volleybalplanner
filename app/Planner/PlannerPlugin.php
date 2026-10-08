<?php

namespace App\Planner;

use App\Planner\Filament\LockedModuleNavigation;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Models\PlannerSetting;
use App\Planner\Support\DesignTokens;
use App\Planner\Support\InitialsAvatarProvider;
use App\Planner\Support\PrimaryColor;
use App\Support\Tenancy\CurrentClub;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Enums\UserMenuPosition;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;

class PlannerPlugin implements Plugin
{
    private static ?Closure $scheduleFooter = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'planner';
    }

    public function register(Panel $panel): void
    {
        $panel
            // The Volleybalplanner design system: Outfit, the planner teal and
            // the slate greys; planner.css carries the matching CSS variables.
            ->font(DesignTokens::FONT)
            ->colors(DesignTokens::palettes())
            ->brandLogo(fn (): View => view('planner::filament.logo'))
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->userMenu(position: UserMenuPosition::Topbar)
            ->navigationGroups([
                NavigationGroup::make(NavigationGroups::PLANNING)->label(__(NavigationGroups::PLANNING)),
                NavigationGroup::make(NavigationGroups::INPUT)->label(__(NavigationGroups::INPUT)),
                NavigationGroup::make(NavigationGroups::MASTER_DATA)->label(__(NavigationGroups::MASTER_DATA)),
            ])
            // Modules outside the plan: visible, locked, opening the upgrade prompt.
            ->navigationItems(LockedModuleNavigation::items())
            ->discoverResources(in: app_path('Planner/Filament/Resources'), for: 'App\\Planner\\Filament\\Resources')
            ->discoverPages(in: app_path('Planner/Filament/Pages'), for: 'App\\Planner\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Planner/Filament/Widgets'), for: 'App\\Planner\\Filament\\Widgets')
            // The club colour as --planner-primary, used by planner.css and the views.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): HtmlString => $this->primaryColorStyle())
            // The bar with search, active planning and account sits above the
            // content column, so the sidebar keeps the logo and club switcher.
            ->renderHook(PanelsRenderHook::CONTENT_BEFORE, fn (): View => view('planner::filament.topbar-mount'));

        // planner.css is the panel theme (Filament theme + Tailwind utilities
        // of the planner views + the day planner styles); planner.js holds the
        // drag and drop. Without built assets (e.g. a fresh checkout running
        // the test suite) the panel falls back on Filament's default theme
        // instead of failing on a missing Vite manifest.
        if ($this->assetsAvailable()) {
            $panel
                ->viteTheme('resources/css/planner.css')
                ->renderHook(PanelsRenderHook::BODY_END, fn (): HtmlString => app(Vite::class)('resources/js/planner.js'));
        }
    }

    private function assetsAvailable(): bool
    {
        return is_file(public_path('hot')) || is_file(public_path('build/manifest.json'));
    }

    private function primaryColorStyle(): HtmlString
    {
        $color = app(CurrentClub::class)->get()
            ? PrimaryColor::from(PlannerSetting::current()->primary_color)
            : PrimaryColor::DEFAULT;

        return new HtmlString("<style>:root{--planner-primary:{$color}}</style>");
    }

    public function boot(Panel $panel): void {}

    /**
     * Extension point for the day schedule and exports, e.g. a sponsor row
     * provided by a separate package. Spec §7.
     */
    public static function scheduleFooterUsing(?Closure $renderer): void
    {
        self::$scheduleFooter = $renderer;
    }

    public static function renderScheduleFooter(): Htmlable|string|null
    {
        return self::$scheduleFooter ? (self::$scheduleFooter)() : null;
    }
}
