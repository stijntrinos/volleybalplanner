<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use STS\FilamentImpersonate\Events\EnterImpersonation;
use STS\FilamentImpersonate\Events\LeaveImpersonation;

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

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // As on the Aetos site: letters in both cases, a digit and a symbol,
        // and in production not one that showed up in a known data breach.
        Password::defaults(function (): Password {
            $password = Password::min(8)->letters()->mixedCase()->numbers()->symbols();

            return $this->app->isProduction() ? $password->uncompromised() : $password;
        });

        // Platform admins may do everything in every club, without a club role.
        Gate::before(fn (User $user): ?bool => $user->isPlatformAdmin() ? true : null);

        // Who acted as whom, and when: changes made while impersonating would
        // otherwise look like the club user's own.
        Event::listen(EnterImpersonation::class, fn (EnterImpersonation $event) => activity('impersonation')
            ->causedBy($event->impersonator)->performedOn($event->impersonated)->log('Impersonation started'));
        Event::listen(LeaveImpersonation::class, function (LeaveImpersonation $event): void {
            $log = activity('impersonation')->causedBy($event->impersonator);
            if ($event->impersonated instanceof Model) {
                $log->performedOn($event->impersonated);
            }
            $log->log('Impersonation ended');
        });

        // The contact form of the public site: a handful per visitor is plenty.
        RateLimiter::for('site-contact', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
    }
}
