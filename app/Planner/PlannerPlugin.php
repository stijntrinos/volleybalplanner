<?php

namespace App\Planner;

use App\Planner\Filament\NavigationGroups;
use App\Planner\Models\PlannerSetting;
use App\Planner\Support\PrimaryColor;
use App\Support\Tenancy\CurrentClub;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
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
            ->navigationGroups([
                NavigationGroup::make(NavigationGroups::PLANNING)->label(__(NavigationGroups::PLANNING)),
                NavigationGroup::make(NavigationGroups::INPUT)->label(__(NavigationGroups::INPUT)),
                NavigationGroup::make(NavigationGroups::MASTER_DATA)->label(__(NavigationGroups::MASTER_DATA)),
            ])
            ->discoverResources(in: app_path('Planner/Filament/Resources'), for: 'App\\Planner\\Filament\\Resources')
            ->discoverPages(in: app_path('Planner/Filament/Pages'), for: 'App\\Planner\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Planner/Filament/Widgets'), for: 'App\\Planner\\Filament\\Widgets')
            // The club colour as --planner-primary, used by planner.css and the views.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): HtmlString => $this->primaryColorStyle());

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
