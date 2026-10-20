<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Site\CookieConsentController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\SeoController;
use App\Http\Middleware\TrackPageViews;
use Illuminate\Support\Facades\Route;

/*
 * The public site of Volleybalplanner. These paths are in ReservedSlugs, so
 * no club can claim them for its own pages under /{club}.
 */
Route::name('site.')->middleware(TrackPageViews::class)->group(function (): void {
    Route::get('/', [PageController::class, 'home'])->name('home');
    Route::get('functies', [PageController::class, 'features'])->name('features');
    Route::get('prijzen', [PageController::class, 'pricing'])->name('pricing');
    Route::get('contact', [PageController::class, 'contact'])->name('contact');
    Route::view('privacy', 'site.pages.privacy')->name('privacy');
    Route::view('voorwaarden', 'site.pages.terms')->name('terms');
    Route::view('cookies', 'site.pages.cookies')->name('cookies');
    Route::post('cookies', [CookieConsentController::class, 'store'])->middleware('throttle:30,1')->name('cookies.store');
    Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');
    Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
    Route::post('contact', [PageController::class, 'storeContact'])
        ->middleware('throttle:site-contact')
        ->name('contact.store');
});

// Invitation links to a club (ClubInvitations).
Route::prefix('uitnodiging/{token}')->name('invitations.')->middleware('throttle:20,1')->group(function (): void {
    Route::get('/', [InvitationController::class, 'show'])->name('show');
    Route::post('accepteren', [InvitationController::class, 'accept'])->name('accept');
    Route::post('afwijzen', [InvitationController::class, 'decline'])->name('decline');
});
