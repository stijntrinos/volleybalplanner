<?php

namespace App\Support\Privacy;

use Illuminate\Http\Request;

final class CookieConsent
{
    public const string COOKIE = 'volleybalplanner_cookie_consent';

    public const int DAYS = 180;

    public function analyticsAllowed(Request $request): bool
    {
        return $this->preference($request) === true;
    }

    public function preference(Request $request): ?bool
    {
        $value = $request->cookie(self::COOKIE);

        return match ($value) {
            'v1:analytics' => true,
            'v1:necessary' => false,
            default => null,
        };
    }
}
