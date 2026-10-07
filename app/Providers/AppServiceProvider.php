<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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

        // Platform admins may do everything in every club, without a club role.
        Gate::before(fn (User $user): ?bool => $user->isPlatformAdmin() ? true : null);
    }
}
