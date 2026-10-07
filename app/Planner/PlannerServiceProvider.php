<?php

namespace App\Planner;

use App\Planner\Console\SyncRolesCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PlannerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/planner'), 'planner');

        // Registered after Filament's panel routes, so /admin and /app never
        // resolve as a club slug (ReservedSlugs keeps clubs from claiming them).
        $this->app->booted(function (): void {
            Route::middleware(['web', 'club'])
                ->prefix('{club}')
                ->name('planner.public.')
                ->group(base_path('routes/planner.php'));
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncRolesCommand::class,
            ]);
        }
    }
}
