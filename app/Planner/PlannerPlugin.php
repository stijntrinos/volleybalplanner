<?php

namespace App\Planner;

use App\Planner\Filament\NavigationGroups;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

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
            ->discoverWidgets(in: app_path('Planner/Filament/Widgets'), for: 'App\\Planner\\Filament\\Widgets');
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
