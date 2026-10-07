<?php

namespace App\Planner;

use App\Models\Club;
use App\Planner\Console\SyncRolesCommand;
use App\Planner\Support\ClubDataPurger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PlannerServiceProvider extends ServiceProvider
{
    /** UserPreference has no policy: it is only ever looked up for the logged-in user. */
    private const POLICIES = [
        Models\Activity::class => Policies\ActivityPolicy::class,
        Models\ActivityColor::class => Policies\ActivityColorPolicy::class,
        Models\Availability::class => Policies\AvailabilityPolicy::class,
        Models\CalendarEvent::class => Policies\CalendarEventPolicy::class,
        Models\ChangeRequest::class => Policies\ChangeRequestPolicy::class,
        Models\Court::class => Policies\CourtPolicy::class,
        Models\FixtureMatch::class => Policies\FixtureMatchPolicy::class,
        Models\HallDuty::class => Policies\HallDutyPolicy::class,
        Models\NevoboChange::class => Policies\NevoboChangePolicy::class,
        Models\PlannerSetting::class => Policies\PlannerSettingPolicy::class,
        Models\ScheduleVersion::class => Policies\ScheduleVersionPolicy::class,
        Models\Team::class => Policies\TeamPolicy::class,
        Models\TeamSource::class => Policies\TeamSourcePolicy::class,
        Models\Training::class => Policies\TrainingPolicy::class,
        Models\Venue::class => Policies\VenuePolicy::class,
        Models\VenueAlias::class => Policies\VenueAliasPolicy::class,
        Models\VolleystarsBlock::class => Policies\VolleystarsBlockPolicy::class,
        Models\VolleystarsTask::class => Policies\VolleystarsTaskPolicy::class,
    ];

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Club::deleting(fn (Club $club) => app(ClubDataPurger::class)->purge($club));

        $this->loadViewsFrom(resource_path('views/planner'), 'planner');

        $this->registerPublicRateLimiters();

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
                Console\SyncNevoboCommand::class,
                Console\RepairMatchCodesCommand::class,
                Console\RepairCourtAssignmentCommand::class,
                Console\ImportDutiesCommand::class,
                Console\CleanDuplicateMatchesCommand::class,
                Console\CleanPoolMatchesCommand::class,
            ]);
        }

        // The nightly Nevobo sync: one queued job per active club.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('planner:sync-nevobo --all')->dailyAt('06:00')->withoutOverlapping();
        });
    }

    /**
     * Throttling of the public club pages, keyed per club and IP so one busy
     * club never blocks a visitor of another club.
     */
    private function registerPublicRateLimiters(): void
    {
        $key = function (Request $request): string {
            $club = $request->route('club');

            return ($club instanceof Club ? $club->slug : (string) $club).'|'.$request->ip();
        };

        RateLimiter::for('planner-public-forms', fn (Request $request): Limit => Limit::perHour(20)->by('forms|'.$key($request)));
        RateLimiter::for('planner-public-feeds', fn (Request $request): Limit => Limit::perMinute(30)->by('feeds|'.$key($request)));
    }
}
