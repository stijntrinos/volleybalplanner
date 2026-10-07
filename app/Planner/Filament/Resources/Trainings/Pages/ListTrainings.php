<?php

namespace App\Planner\Filament\Resources\Trainings\Pages;

use App\Planner\Enums\Source;
use App\Planner\Filament\Resources\Trainings\TrainingResource;
use App\Planner\Models\Training;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListTrainings extends ListRecords
{
    protected static string $resource = TrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('Training toevoegen'))->icon(Heroicon::OutlinedPlus)];
    }

    public function getSubheading(): ?string
    {
        return __('Beheer het vaste weekschema dat wordt gebruikt bij Thuiswedstrijden en bij FTZ-conflictcontroles.');
    }

    public function getTabs(): array
    {
        $workdays = array_slice(Training::WEEKDAYS, 0, 5);
        $weekend = array_slice(Training::WEEKDAYS, 5);

        return [
            'all' => Tab::make(__('Volledig schema'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->badge(Training::query()->count()),
            'workdays' => Tab::make(__('Maandag t/m vrijdag'))
                ->badge(Training::query()->whereIn('weekday', $workdays)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('weekday', $workdays)),
            'weekend' => Tab::make(__('Weekend'))
                ->badge(Training::query()->whereIn('weekday', $weekend)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('weekday', $weekend)),
            'import' => Tab::make(__('Geïmporteerd'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->badge(Training::query()->where('source', Source::Import)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('source', Source::Import)),
            'manual' => Tab::make(__('Handmatig'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->badge(Training::query()->where('source', Source::Manual)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('source', Source::Manual)),
        ];
    }
}
