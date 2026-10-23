<?php

namespace App\Planner\Support;

use App\Models\Club;
use App\Planner\Models\PlannerSetting;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatars drawn in-house: a circle with initials, as the design system shows
 * the club (in its own colour) and the signed-in user (in ink). No request
 * leaves the server for them, unlike the ui-avatars.com default.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public const INK = '#15232C';

    public function get(Model $record): string
    {
        $name = Filament::getNameForDefaultAvatar($record);
        $background = $record instanceof Club ? self::clubColor($record) : self::INK;

        return self::dataUri(self::initials($name), $background);
    }

    public static function initials(string $name): string
    {
        $words = preg_split('/[\s\-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = collect($words)->map(fn (string $word): string => mb_substr($word, 0, 1))->take(2)->implode('');

        return mb_strtoupper($initials ?: '?');
    }

    public static function dataUri(string $initials, string $background): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">'
            .'<circle cx="32" cy="32" r="32" fill="'.htmlspecialchars($background, ENT_QUOTES).'"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" font-family="Outfit, Segoe UI, system-ui, sans-serif" font-size="'.(mb_strlen($initials) > 1 ? 24 : 28).'" font-weight="700" fill="#FFFFFF">'
            .htmlspecialchars($initials, ENT_QUOTES).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private static function clubColor(Club $club): string
    {
        $color = PlannerSetting::withoutGlobalScopes()
            ->where('club_id', $club->getKey())
            ->value('primary_color');

        return PrimaryColor::from(is_string($color) ? $color : null);
    }
}
