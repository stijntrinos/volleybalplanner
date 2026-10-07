<?php

namespace App\Planner\Filament\Resources\CalendarEvents\Tables;

use App\Planner\Models\ActivityColor;
use App\Planner\Models\CalendarEvent;
use App\Planner\Support\DateFormat;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CalendarEventsTable
{
    private const MOMENT_ONGOING = 'ongoing';

    private const MOMENT_PAST = 'past';

    private const MOMENT_UPCOMING = 'upcoming';

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Activiteit'))
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (CalendarEvent $record): string => static::periodLabel($record))
                    ->icon('heroicon-o-calendar')
                    ->iconColor('gray')
                    ->wrap(),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->color(fn (CalendarEvent $record): array => Color::hex($record->type_color ?: '#6B7280'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('moment')
                    ->label(__('Moment'))
                    ->state(fn (CalendarEvent $record): string => static::momentLabel($record))
                    ->badge()
                    ->color(fn (CalendarEvent $record): string => static::momentColor($record))
                    ->icon(fn (CalendarEvent $record): string => static::momentIcon($record)),
                TextColumn::make('duration')
                    ->label(__('Duur'))
                    ->state(fn (CalendarEvent $record): string => static::durationLabel($record))
                    ->alignCenter(),
                TextColumn::make('updated_at')
                    ->label(__('Gewijzigd'))
                    ->since()
                    ->dateTimeTooltip('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(fn () => ActivityColor::query()->orderBy('type')->pluck('type', 'type'))
                    ->searchable()
                    ->preload(),
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label(__('Vanaf'))->native(false)->displayFormat('d-m-Y'),
                        DatePicker::make('until')->label(__('Tot en met'))->native(false)->displayFormat('d-m-Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('end_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('start_date', '<=', $date))),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start_date')
            ->striped()
            ->emptyStateHeading(__('Nog geen kalenderactiviteiten'))
            ->emptyStateDescription(__('Voeg vakanties, feestdagen en Nevobo-speelrondes toe aan de planning.'));
    }

    public static function periodLabel(CalendarEvent $record): string
    {
        if ($record->start_date->isSameDay($record->end_date)) {
            return (string) DateFormat::withWeekday($record->start_date, short: true);
        }

        return DateFormat::date($record->start_date).' – '.DateFormat::date($record->end_date);
    }

    public static function durationLabel(CalendarEvent $record): string
    {
        $days = (int) $record->start_date->diffInDays($record->end_date) + 1;

        return $days === 1 ? __('1 dag') : __(':count dagen', ['count' => $days]);
    }

    public static function momentLabel(CalendarEvent $record): string
    {
        return match (static::moment($record)) {
            self::MOMENT_PAST => __('Afgelopen'),
            self::MOMENT_ONGOING => __('Bezig'),
            default => ($days = (int) today()->diffInDays($record->start_date)) <= 14
                ? __('Over :count dagen', ['count' => $days])
                : __('Aankomend'),
        };
    }

    public static function momentColor(CalendarEvent $record): string
    {
        return match (static::moment($record)) {
            self::MOMENT_ONGOING => 'success',
            self::MOMENT_PAST => 'gray',
            default => 'info',
        };
    }

    public static function momentIcon(CalendarEvent $record): string
    {
        return match (static::moment($record)) {
            self::MOMENT_ONGOING => 'heroicon-o-play-circle',
            self::MOMENT_PAST => 'heroicon-o-check-circle',
            default => 'heroicon-o-clock',
        };
    }

    private static function moment(CalendarEvent $record): string
    {
        return match (true) {
            $record->end_date->isBefore(today()) => self::MOMENT_PAST,
            $record->start_date->isAfter(today()) => self::MOMENT_UPCOMING,
            default => self::MOMENT_ONGOING,
        };
    }
}
