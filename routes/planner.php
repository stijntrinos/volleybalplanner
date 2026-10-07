<?php

use App\Planner\Http\Controllers\ClubHomeController;
use App\Planner\Http\Controllers\RssFeedController;
use App\Planner\Http\Middleware\SetFrameOptions;
use App\Planner\Livewire\DutyChangeRequestForm;
use App\Planner\Livewire\MatchChangeRequestForm;
use App\Planner\Livewire\MatchOverview;
use Illuminate\Support\Facades\Route;

/*
 * Public club pages. Loaded by PlannerServiceProvider with prefix "{club}",
 * middleware ['web', 'club'] and name prefix "planner.public.".
 * Rate limiters 'planner-public-forms' and 'planner-public-feeds' are keyed
 * per club and IP (PlannerServiceProvider).
 */

Route::middleware(SetFrameOptions::class)->group(function (): void {
    Route::get('/', ClubHomeController::class)->name('home');

    // The overview is planner core: every club has it.
    Route::get('wedstrijden', MatchOverview::class)->name('matches');

    Route::middleware(['feature:change_requests', 'throttle:planner-public-forms'])->group(function (): void {
        Route::get('wedstrijd-verzetten', MatchChangeRequestForm::class)->name('match-change-request');
        Route::get('dienst-wijzigen', DutyChangeRequestForm::class)
            ->middleware('feature:duties')
            ->name('duty-change-request');
    });
});

// Feed readers poll well within 30 per minute.
Route::middleware(['feature:rss_feeds', 'throttle:planner-public-feeds'])->group(function (): void {
    Route::get('rss', [RssFeedController::class, 'central'])->name('rss.central');
    Route::prefix('rss')->name('rss.')->group(function (): void {
        Route::get('thuis', [RssFeedController::class, 'home'])->name('home');
        Route::get('sporthal/{name}', [RssFeedController::class, 'venue'])->name('venue');
        Route::get('team/{code}', [RssFeedController::class, 'team'])->name('team');
        Route::get('dag/{date}', [RssFeedController::class, 'day'])->name('day');
    });
});
