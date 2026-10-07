<?php

namespace App\Planner\Filament\Resources\Venues\Pages;

use App\Planner\Filament\Resources\Venues\VenueResource;
use App\Planner\Models\Venue;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListVenues extends ListRecords
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('Sporthal toevoegen'))->icon(Heroicon::OutlinedPlus),
        ];
    }

    public function getSubheading(): ?string
    {
        return __('Beheer locaties, velden en alternatieve importnamen voor de wedstrijd- en trainingsplanner.');
    }

    public function getTabs(): array
    {
        return [
            'planner' => Tab::make(__('In de planner'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->badge(Venue::query()->where('is_active', true)->where('is_managed', true)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_active', true)->where('is_managed', true)),
            'other' => Tab::make(__('Overige locaties'))
                ->icon(Heroicon::OutlinedMapPin)
                ->badge(Venue::query()->where('is_active', true)->where('is_managed', false)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_active', true)->where('is_managed', false)),
            'inactive' => Tab::make(__('Inactief'))
                ->icon(Heroicon::OutlinedArchiveBox)
                ->badge(Venue::query()->where('is_active', false)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_active', false)),
            'all' => Tab::make(__('Alles'))->badge(Venue::query()->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'planner';
    }
}
