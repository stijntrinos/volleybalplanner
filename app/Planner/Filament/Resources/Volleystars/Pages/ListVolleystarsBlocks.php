<?php

namespace App\Planner\Filament\Resources\Volleystars\Pages;

use App\Planner\Filament\Resources\Volleystars\VolleystarsBlockResource;
use App\Planner\Models\VolleystarsBlock;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListVolleystarsBlocks extends ListRecords
{
    protected static string $resource = VolleystarsBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('VolleyStars-blok toevoegen'))->icon(Heroicon::OutlinedPlus)];
    }

    public function getSubheading(): ?string
    {
        return __('Plan opbouw, tellen en afbouw per VolleyStars-dag en verdeel de bijbehorende taken over teams.');
    }

    public function getTabs(): array
    {
        return [
            'current' => Tab::make(__('Actueel & aankomend'))->icon(Heroicon::OutlinedCalendarDays)
                ->badge(VolleystarsBlock::query()->whereDate('date', '>=', today())->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('date', '>=', today())),
            'past' => Tab::make(__('Verlopen'))->icon(Heroicon::OutlinedArchiveBox)
                ->badge(VolleystarsBlock::query()->whereDate('date', '<', today())->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('date', '<', today())),
            'all' => Tab::make(__('Alles'))->badge(VolleystarsBlock::query()->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'current';
    }
}
