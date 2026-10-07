<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Club;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Every club as a card on the platform dashboard; a click opens the club's
 * workspace in a new tab.
 */
class ClubsOverview extends Widget
{
    protected string $view = 'filament.platform.widgets.clubs-overview';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /** @return Collection<int, array{name: string, slug: string, active: bool, users: ?int, modules: non-negative-int, created: string, url: ?string}> */
    public function getClubs(): Collection
    {
        $panel = Filament::getPanel('club');

        return Club::query()
            ->withCount('users')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Club $club): array => [
                'name' => $club->name,
                'slug' => $club->slug,
                'active' => $club->is_active,
                'users' => $club->users_count,
                'modules' => count($club->features ?? []),
                'created' => $club->created_at?->format('d-m-Y') ?? '',
                'url' => $panel->getUrl($club),
            ]);
    }
}
