<?php

namespace App\Planner\Filament\Widgets;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Route;

/**
 * A reminder on the dashboard as long as the signed-in account is protected
 * by its password alone. The planner holds contact details of members, so
 * the committee is nudged towards two-factor authentication or a passkey.
 */
class AccountSecurityNotice extends Widget
{
    protected static ?int $sort = -4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'planner::filament.widgets.account-security-notice';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && !$user->hasStrongAuthentication();
    }

    /** @return array{url: ?string} */
    protected function getViewData(): array
    {
        $route = 'filament.'.Filament::getCurrentPanel()?->getId().'.pages.account';

        return [
            'url' => Route::has($route) ? route($route, ['tenant' => Filament::getTenant()]) : null,
        ];
    }
}
