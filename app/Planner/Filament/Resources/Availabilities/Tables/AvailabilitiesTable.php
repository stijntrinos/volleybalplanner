<?php

namespace App\Planner\Filament\Resources\Availabilities\Tables;

use App\Planner\Enums\AvailabilityScope;
use App\Planner\Enums\AvailabilityStatus;
use App\Planner\Models\Availability;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class AvailabilitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('venue.name')
                    ->label(__('Sporthal'))
                    ->searchable()
                    ->weight('semibold')
                    ->sortable()
                    ->description(fn (Availability $record): string => $record->court ? __('Specifiek veld') : __('Volledige sporthal')),
                TextColumn::make('court.name')
                    ->label(__('Veld'))
                    ->placeholder(__('Alle velden'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('validity')
                    ->label(__('Geldig op'))
                    ->state(fn (Availability $record): string => self::validity($record))
                    ->description(fn (Availability $record): string => match ($record->scope) {
                        AvailabilityScope::Date => __('Eenmalig'),
                        AvailabilityScope::Weekday => __('Wekelijks'),
                        AvailabilityScope::Period => __('Periode'),
                        default => '',
                    })
                    ->icon(fn (Availability $record): string => match ($record->scope) {
                        AvailabilityScope::Date => 'heroicon-o-calendar',
                        AvailabilityScope::Weekday => 'heroicon-o-arrow-path',
                        AvailabilityScope::Period => 'heroicon-o-calendar-days',
                        default => 'heroicon-o-clock',
                    }),
                TextColumn::make('window')
                    ->label(__('Tijd'))
                    ->state(fn (Availability $record): string => $record->window())
                    ->badge()
                    ->color(fn (Availability $record): string => $record->hasTimeWindow() ? 'info' : 'gray'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (?AvailabilityStatus $state): string => match ($state) {
                        AvailabilityStatus::Available => 'success',
                        AvailabilityStatus::Partial => 'warning',
                        AvailabilityStatus::Closed => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('note')
                    ->label(__('Notitie'))
                    ->searchable()
                    ->limit(55)
                    ->tooltip(fn (Availability $record): ?string => $record->note)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(AvailabilityStatus::class),
                SelectFilter::make('scope')
                    ->label(__('Herhaling'))
                    ->options([
                        AvailabilityScope::Date->value => __('Specifieke datum'),
                        AvailabilityScope::Weekday->value => __('Vaste weekdag'),
                        AvailabilityScope::Period->value => __('Periode'),
                    ]),
                SelectFilter::make('venue_id')
                    ->label(__('Sporthal'))
                    ->relationship('venue', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make()->label(__('Aanpassen')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->groups([
                Group::make('venue.name')
                    ->label(__('Sporthal'))
                    ->collapsible(),
            ])
            ->defaultGroup('venue.name')
            ->defaultSort('date')
            ->emptyStateHeading(__('Nog geen beschikbaarheidsregels'))
            ->emptyStateDescription(__('Voeg een wekelijkse basisregel of een tijdelijke uitzondering voor een sporthal of veld toe.'))
            ->emptyStateIcon('heroicon-o-calendar-days');
    }

    private static function validity(Availability $record): string
    {
        $everyWeekday = [
            0 => __('Iedere zondag'), 1 => __('Iedere maandag'), 2 => __('Iedere dinsdag'), 3 => __('Iedere woensdag'),
            4 => __('Iedere donderdag'), 5 => __('Iedere vrijdag'), 6 => __('Iedere zaterdag'),
        ];

        return match ($record->scope) {
            AvailabilityScope::Date => $record->date?->format('d-m-Y') ?? '—',
            AvailabilityScope::Weekday => $everyWeekday[$record->weekday] ?? '—',
            AvailabilityScope::Period => __(':from t/m :until', [
                'from' => $record->period_start?->format('d-m-Y') ?? '—',
                'until' => $record->period_end?->format('d-m-Y') ?? '—',
            ]),
            default => '—',
        };
    }
}
