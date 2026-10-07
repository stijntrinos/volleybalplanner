<?php

namespace App\Planner\Filament\Resources\Trainings\Tables;

use App\Planner\Enums\Source;
use App\Planner\Filament\Resources\Trainings\TrainingResource;
use App\Planner\Models\Training;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class TrainingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('team.code')
                    ->label(__('Team'))
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Training $record): ?string => $record->team?->name)
                    ->placeholder(__('Algemene training')),
                TextColumn::make('time_slot')
                    ->label(__('Tijd'))
                    ->state(fn (Training $record): string => substr((string) $record->start_time, 0, 5).' – '.substr((string) $record->end_time, 0, 5))
                    ->description(fn (Training $record): string => self::durationLabel($record))
                    ->icon('heroicon-o-clock'),
                TextColumn::make('venue.name')
                    ->label(__('Locatie'))
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Training $record): string => collect([
                        __('Veld :court', ['court' => $record->court]),
                        $record->court_type,
                    ])->filter()->implode(' · ')),
                TextColumn::make('source')
                    ->label(__('Herkomst'))
                    ->badge()
                    ->color(fn (?Source $state): string => $state === Source::Import ? 'info' : 'gray')
                    ->formatStateUsing(fn (?Source $state): string => $state === Source::Import ? __('Import') : __('Handmatig')),
                TextColumn::make('notes')
                    ->label(__('Opmerking'))
                    ->searchable()
                    ->limit(45)
                    ->tooltip(fn (Training $record): ?string => $record->notes)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('weekday')->label(__('Trainingsdag'))->options(TrainingResource::weekdayOptions()),
                SelectFilter::make('venue')->label(__('Sporthal'))->relationship('venue', 'name')->searchable()->preload(),
                SelectFilter::make('team')->label(__('Team'))->relationship('team', 'name')->searchable()->preload(),
            ])
            ->recordActions([EditAction::make()->label(__('Aanpassen'))])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->groups([
                Group::make('weekday')
                    ->label(__('Trainingsdag'))
                    ->getTitleFromRecordUsing(fn (Training $record): string => __(ucfirst((string) $record->weekday)))
                    ->orderQueryUsing(fn ($query, string $direction) => $query->orderByRaw(self::weekdayOrderSql().' '.($direction === 'desc' ? 'desc' : 'asc')))
                    ->collapsible(),
            ])
            ->defaultGroup('weekday')
            ->defaultSort('start_time')
            ->emptyStateHeading(__('Nog geen trainingsschema'))
            ->emptyStateDescription(__('Voeg een vast trainingsmoment toe of importeer het trainingsschema via Planning importeren.'))
            ->emptyStateIcon('heroicon-o-academic-cap');
    }

    private static function durationLabel(Training $training): string
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', substr((string) $training->start_time, 0, 5)) + [0, 0]);
        [$endHour, $endMinute] = array_map('intval', explode(':', substr((string) $training->end_time, 0, 5)) + [0, 0]);
        $duration = ($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute);

        return $duration >= 60 && $duration % 60 === 0
            ? __(':count uur', ['count' => $duration / 60])
            : __(':count minuten', ['count' => $duration]);
    }

    private static function weekdayOrderSql(): string
    {
        $cases = collect(Training::WEEKDAYS)
            ->map(fn (string $day, int $index): string => "WHEN '{$day}' THEN ".($index + 1))
            ->implode(' ');

        return "CASE weekday {$cases} ELSE 8 END";
    }
}
