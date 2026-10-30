<?php

namespace App\Planner\Filament\Resources\ScheduleVersions\Tables;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Exceptions\PublishBlocked;
use App\Planner\Filament\Resources\ScheduleVersions\Pages\ListScheduleVersions;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Services\ScheduleVersionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ScheduleVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Planningversie'))
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (ScheduleVersion $record): string => $record->isPublished()
                        ? __('Dit is de actieve planning')
                        : ($record->note ?: __('Geen interne notitie')))
                    ->icon(fn (ScheduleVersion $record): Heroicon => $record->isPublished()
                        ? Heroicon::OutlinedCheckCircle
                        : Heroicon::OutlinedDocumentDuplicate)
                    ->iconColor(fn (ScheduleVersion $record): string => $record->isPublished() ? 'success' : 'gray')
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (ScheduleVersionStatus $state): string => match ($state) {
                        ScheduleVersionStatus::Published => __('Actieve planning'),
                        ScheduleVersionStatus::Draft => __('Concept'),
                        ScheduleVersionStatus::Archived => __('Gearchiveerd'),
                    })
                    ->icon(fn (ScheduleVersionStatus $state): Heroicon => match ($state) {
                        ScheduleVersionStatus::Published => Heroicon::OutlinedCheckCircle,
                        ScheduleVersionStatus::Draft => Heroicon::OutlinedPencilSquare,
                        ScheduleVersionStatus::Archived => Heroicon::OutlinedArchiveBox,
                    })
                    ->color(fn (ScheduleVersionStatus $state): string => match ($state) {
                        ScheduleVersionStatus::Published => 'success',
                        ScheduleVersionStatus::Draft => 'warning',
                        ScheduleVersionStatus::Archived => 'gray',
                    }),
                TextColumn::make('matches_count')
                    ->label(__('Wedstrijden'))
                    ->counts('matches')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
                TextColumn::make('published_at')
                    ->label(__('Publicatie'))
                    ->dateTime('d-m-Y H:i')
                    ->placeholder(__('Nog niet gepubliceerd'))
                    ->description(fn (ScheduleVersion $record): ?string => $record->published_at?->diffForHumans()),
                TextColumn::make('updated_at')
                    ->label(__('Laatst gewijzigd'))
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->since()
                    ->dateTimeTooltip('d-m-Y H:i'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(ScheduleVersionStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('publish')
                        ->label(__('Actief maken'))
                        ->icon(Heroicon::OutlinedRocketLaunch)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(__('Versie publiceren'))
                        ->modalDescription(__('Deze versie wordt de actieve planning. De huidige actieve planning wordt automatisch gearchiveerd.'))
                        ->modalSubmitActionLabel(__('Ja, actief maken'))
                        ->successNotificationTitle(__('Planningversie is nu actief'))
                        ->authorize(fn (ScheduleVersion $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->visible(fn (ScheduleVersion $record): bool => !$record->isPublished())
                        ->action(function (ScheduleVersion $record, Action $action, ListScheduleVersions $livewire): void {
                            try {
                                app(ScheduleVersionService::class)->publish($record);
                            } catch (PublishBlocked $blocked) {
                                // The confirmation makes way for the overview, so the
                                // user sees why instead of a generic error message.
                                $livewire->replaceMountedAction('publishConflicts', [
                                    'version' => $record->name,
                                    'conflicts' => $blocked->conflicts,
                                ]);
                                // Without a status Filament assumes success and reports
                                // "now active" while nothing was published.
                                $action->failure();

                                return;
                            }

                            $action->success();
                        }),
                    EditAction::make()
                        ->label(__('Bewerken')),
                    Action::make('duplicate')
                        ->label(__('Dupliceren'))
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->authorize(fn (): bool => auth()->user()?->can('create', ScheduleVersion::class) ?? false)
                        ->schema([
                            TextInput::make('name')
                                ->label(__('Naam van de kopie'))
                                ->helperText(__('De kopie wordt als nieuw concept aangemaakt.'))
                                ->required()
                                ->maxLength(255)
                                ->default(fn (ScheduleVersion $record): string => __('Kopie van :name', ['name' => $record->name])),
                        ])
                        ->successNotificationTitle(__('Conceptkopie aangemaakt'))
                        ->action(function (ScheduleVersion $record, array $data, Action $action): void {
                            app(ScheduleVersionService::class)->duplicate($record, $data['name'], auth()->id());
                            $action->success();
                        }),
                    Action::make('archive')
                        ->label(__('Archiveren'))
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalDescription(__('De versie blijft bewaard, maar verhuist naar het archief.'))
                        ->successNotificationTitle(__('Planningversie gearchiveerd'))
                        ->authorize(fn (ScheduleVersion $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->visible(fn (ScheduleVersion $record): bool => !$record->isArchived())
                        ->action(function (ScheduleVersion $record, Action $action): void {
                            app(ScheduleVersionService::class)->archive($record);
                            $action->success();
                        }),
                    Action::make('reactivate')
                        ->label(__('Terugzetten naar concept'))
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->successNotificationTitle(__('Planningversie is weer een concept'))
                        ->authorize(fn (ScheduleVersion $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->visible(fn (ScheduleVersion $record): bool => $record->isArchived())
                        ->action(function (ScheduleVersion $record, Action $action): void {
                            app(ScheduleVersionService::class)->reactivate($record);
                            $action->success();
                        }),
                    DeleteAction::make()
                        ->visible(fn (ScheduleVersion $record): bool => !$record->isPublished()),
                ])
                    ->label(__('Acties'))
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->color('gray')
                    ->dropdownPlacement('bottom-end')
                    ->button(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading(__('Nog geen planningversies'))
            ->emptyStateDescription(__('Maak een conceptversie om wedstrijden te importeren en in te plannen.'));
    }
}
