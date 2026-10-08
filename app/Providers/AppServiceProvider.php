<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Tenancy\ClubIsReadOnly;
use App\Support\Tenancy\CurrentClub;
use App\Support\Tenancy\ReadOnlyClub;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use STS\FilamentImpersonate\Events\EnterImpersonation;
use STS\FilamentImpersonate\Events\LeaveImpersonation;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

use function Livewire\on;

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

        // A read-only club refuses every write, also to platform admins (they
        // extend the subscription in /admin). Otherwise platform admins may do
        // everything in every club, without a club role.
        Gate::before(function (User $user, string $ability): ?bool {
            if (ReadOnlyClub::blocks($ability)) {
                return false;
            }

            return $user->isPlatformAdmin() ? true : null;
        });

        // A write refused in read-only mode becomes a notification instead of
        // a 403 modal over the planner. Only for actions: opening a page that
        // is refused (a create form) stays a 403.
        on('exception', function (mixed $target, Throwable $exception, callable $stopPropagation): void {
            if (!Livewire::isLivewireRequest()) {
                return;
            }

            $refused = $exception instanceof ClubIsReadOnly
                || $exception instanceof AuthorizationException
                || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 403);

            if ($refused && ReadOnlyClub::active()) {
                Notification::make()
                    ->title(__('Alleen bekijken'))
                    ->body((new ClubIsReadOnly)->getMessage())
                    ->danger()
                    ->send();
                $stopPropagation();
            }
        });

        // Who acted as whom, and when: changes made while impersonating would
        // otherwise look like the club user's own.
        Event::listen(EnterImpersonation::class, function (EnterImpersonation $event): void {
            $log = activity('impersonation');
            if ($event->impersonator instanceof Model) {
                $log->causedBy($event->impersonator);
            }
            if ($event->impersonated instanceof Model) {
                $log->performedOn($event->impersonated);
            }
            $log->log('Impersonation started');
        });
        Event::listen(LeaveImpersonation::class, function (LeaveImpersonation $event): void {
            $log = activity('impersonation');
            if ($event->impersonator instanceof Model) {
                $log->causedBy($event->impersonator);
            }
            if ($event->impersonated instanceof Model) {
                $log->performedOn($event->impersonated);
            }
            $log->log('Impersonation ended');
        });

        // The contact form of the public site: a handful per visitor is plenty.
        RateLimiter::for('site-contact', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
    }
}
