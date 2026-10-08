<?php

namespace App\Filament\Platform\Resources\Activities;

use App\Filament\Platform\Resources\Activities\Pages\ListActivities;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only log of what happened: moves, imports, published versions and
 * who acted as whom. Subjects are club data, so only their type and id are
 * shown here (loading them needs that club as context).
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'activiteit';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Activiteit';

    protected static ?string $modelLabel = 'activiteit';

    protected static ?string $pluralModelLabel = 'activiteit';

    protected static ?int $navigationSort = 30;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Wanneer')->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('log_name')->label('Soort')->badge()->color('gray')
                    ->formatStateUsing(fn (?string $state): string => self::logLabel($state)),
                TextColumn::make('description')->label('Wat')->wrap(),
                TextColumn::make('causer.name')->label('Door')->placeholder('Systeem')->searchable(),
                TextColumn::make('subject_type')->label('Onderwerp')
                    ->formatStateUsing(fn (Activity $record): string => $record->subject_type
                        ? class_basename($record->subject_type).' #'.$record->subject_id
                        : '—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('log_name')->label('Soort')->options(fn (): array => Activity::query()
                    ->distinct()->orderBy('log_name')->pluck('log_name', 'log_name')
                    ->map(fn (?string $name): string => self::logLabel($name))->all()),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (Activity $record): string => $record->description)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Sluiten')
                    ->schema(fn (Activity $record): array => [
                        TextEntry::make('when')->label('Wanneer')->state($record->created_at?->format('d-m-Y H:i:s')),
                        TextEntry::make('who')->label('Door')->state($record->causer instanceof User ? $record->causer->name : 'Systeem'),
                        KeyValueEntry::make('properties')->label('Gegevens')
                            ->state(collect(Arr::dot($record->properties?->toArray() ?? []))
                                ->map(fn (mixed $value): string => is_scalar($value) || $value === null ? (string) json_encode($value) : Str::limit((string) json_encode($value), 200))
                                ->all()),
                    ]),
            ])
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }

    private static function logLabel(?string $name): string
    {
        return match ($name) {
            'impersonation' => 'Inloggen als',
            'planner-move' => 'Verplaatsen',
            'planner-duties' => 'Diensten',
            null, 'default' => 'Algemeen',
            default => Str::of($name)->after('planner-')->replace('-', ' ')->ucfirst()->toString(),
        };
    }
}
