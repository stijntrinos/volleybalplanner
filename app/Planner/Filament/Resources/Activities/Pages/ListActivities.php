<?php

namespace App\Planner\Filament\Resources\Activities\Pages;

use App\Planner\Filament\Resources\Activities\ActivityResource;
use App\Planner\Models\Activity;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('Zaalactiviteit toevoegen'))->icon(Heroicon::OutlinedPlus)];
    }

    public function getSubheading(): ?string
    {
        return __('Blokkeer een sporthal of veld voor activiteiten buiten wedstrijden. De lijst toont standaard alleen jouw actieve planningversie.');
    }

    public function getTabs(): array
    {
        return [
            'current' => Tab::make(__('Actueel & aankomend'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->badge(Activity::query()->whereDate('date', '>=', today())->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('date', '>=', today())),
            'past' => Tab::make(__('Verlopen'))
                ->icon(Heroicon::OutlinedArchiveBox)
                ->badge(Activity::query()->whereDate('date', '<', today())->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('date', '<', today())),
            'all' => Tab::make(__('Alles'))->badge(Activity::query()->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'current';
    }
}
