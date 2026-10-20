<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCookieConsentRequest;
use App\Support\Privacy\CookieConsent;
use Illuminate\Http\RedirectResponse;

class CookieConsentController extends Controller
{
    public function store(StoreCookieConsentRequest $request): RedirectResponse
    {
        return back(fallback: route('site.home'))
            ->with('cookie-preferences-saved', true)
            ->cookie(CookieConsent::COOKIE, $request->boolean('analytics') ? 'v1:analytics' : 'v1:necessary', CookieConsent::DAYS * 24 * 60, '/', null, $request->isSecure(), true, false, 'lax');
    }
}
