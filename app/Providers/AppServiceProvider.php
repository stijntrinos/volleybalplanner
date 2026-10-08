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
use Illuminate\Support\ServiceProvider;
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
