<?php

namespace App\Providers;

use App\Support\Tenancy\CurrentClub;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentClub::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // An attribute missing from $fillable fails loudly instead of vanishing on save.
        Model::preventSilentlyDiscardingAttributes(!$this->app->isProduction());
    }
}
