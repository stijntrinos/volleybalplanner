<?php

use App\Http\Controllers\Site\PageController;
use Illuminate\Support\Facades\Route;

/*
 * The public site of Volleybalplanner. These paths are in ReservedSlugs, so
 * no club can claim them for its own pages under /{club}.
 */
Route::name('site.')->group(function (): void {
    Route::get('/', [PageController::class, 'home'])->name('home');
    Route::get('functies', [PageController::class, 'features'])->name('features');
    Route::get('prijzen', [PageController::class, 'pricing'])->name('pricing');
    Route::get('contact', [PageController::class, 'contact'])->name('contact');
    Route::post('contact', [PageController::class, 'storeContact'])
        ->middleware('throttle:site-contact')
        ->name('contact.store');
});
