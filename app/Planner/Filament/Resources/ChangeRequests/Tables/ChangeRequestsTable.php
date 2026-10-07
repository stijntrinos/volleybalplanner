<?php

namespace App\Planner\Filament\Resources\ChangeRequests\Tables;

use App\Planner\Enums\ChangeRequestKind;
use App\Planner\Enums\ChangeRequestStatus;
use App\Planner\Enums\ContactPreference;
use App\Planner\Filament\Resources\ChangeRequests\Schemas\ChangeRequestInfolist;
use App\Planner\Models\ChangeRequest;
use App\Planner\Services\ChangeRequestService;
use App\Planner\Support\DateFormat;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ChangeRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['team', 'match']))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Binnengekomen'))
                    ->formatStateUsing(fn ($state): string => DateFormat::dateTime($state) ?? '—')
                    ->sortable(),
                TextColumn::make('team.code')->label(__('Team'))->sortable()->searchable(),
                TextColumn::make('kind')
                    ->label(__('Soort'))
                    ->badge()
                    ->color(fn (?ChangeRequestKind $state): string => $state === ChangeRequestKind::Duty ? 'info' : 'gray'),
                TextColumn::make('subject')
                    ->label(__('Onderwerp'))
                    ->state(fn (ChangeRequest $record): string => $record->subjectDescription())
                    ->description(fn (ChangeRequest $record): string => trim(
                        $record->isDuty()
                            ? collect([
                                DateFormat::date($record->duty_date),
                                $record->duty_time,
                                $record->duty_location,
                            ])->filter()->implode(' · ')
                            : $record->match_code.' · '.(DateFormat::date($record->match?->date) ?? __('datum onbekend')),
                    ))
                    ->wrap(),
                TextColumn::make('reason')
                    ->label(__('Reden'))
                    ->state(fn (ChangeRequest $record): string => $record->reasonText())
                    ->description(fn (ChangeRequest $record): string => collect([
                        $record->proposalText() ? __('Voorstel: :proposal', ['proposal' => $record->proposalText()]) : null,
                        str((string) $record->explanation)->limit(80)->value(),
                    ])->filter()->implode(' · '))
                    ->wrap(),
                TextColumn::make('name')
                    ->label(__('Aanvrager'))
                    ->description(fn (ChangeRequest $record): string => $record->contact_preference === ContactPreference::Whatsapp && filled($record->phone)
                        ? __('WhatsApp: :phone', ['phone' => $record->phone])
                        : (string) $record->email)
                    ->searchable(false),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('note')->label(__('Notitie'))->wrap()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Parked is not decided yet; those requests stay in view next
                // to the new ones by default.
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->multiple()
                    ->options(ChangeRequestStatus::class)
                    ->default(array_map(fn (ChangeRequestStatus $status): string => $status->value, ChangeRequestStatus::undecided())),
                SelectFilter::make('kind')->label(__('Soort'))->options(ChangeRequestKind::class),
                SelectFilter::make('team_id')->label(__('Team'))->relationship('team', 'code'),
            ])
            // A click on the row opens the whole request. The explanation and
            // the handling do not fit in the columns.
            ->recordAction('view')
            ->recordActions([
                ActionGroup::make([
                    self::viewAction(),
                    ...self::handleActions(),
                ]),
            ]);
    }

    /**
     * The request as a whole, with the same handling buttons below it: whoever
     * reads it wants to act on it without closing the modal.
     */
    private static function viewAction(): ViewAction
    {
        return ViewAction::make('view')
            ->label(__('Bekijken'))
            ->modalHeading(fn (ChangeRequest $record): string => $record->subjectDescription())
            ->schema(fn (Schema $schema): Schema => ChangeRequestInfolist::configure($schema))
            ->extraModalFooterActions(array_map(
                fn (Action $action): Action => $action->cancelParentActions(),
                self::handleActions(),
            ));
    }

    /** @return list<Action> */
    private static function handleActions(): array
    {
        return [
            self::handleAction('accept', __('Accepteren'), ChangeRequestStatus::Accepted, 'success', 'heroicon-o-check'),
            self::handleAction('reject', __('Afwijzen'), ChangeRequestStatus::Rejected, 'danger', 'heroicon-o-x-mark'),
            self::handleAction('park', __('Parkeren'), ChangeRequestStatus::Parked, 'info', 'heroicon-o-pause-circle'),
            self::handleAction('reopen', __('Weer openzetten'), ChangeRequestStatus::Open, 'gray', 'heroicon-o-arrow-uturn-left'),
        ];
    }

    /**
     * Parking keeps a request undecided but takes it out of the new-requests
     * counter: the planning committee is working on it.
     *
     * Accepting does not change the planning automatically. This action only
     * records what was decided; the real change happens in the planner.
     */
    private static function handleAction(string $name, string $label, ChangeRequestStatus $status, string $color, string $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->authorize(fn (ChangeRequest $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->visible(fn (ChangeRequest $record): bool => $record->status !== $status)
            ->schema([
                Textarea::make('note')
                    ->label(__('Notitie voor wedstrijdzaken'))
                    ->default(fn (ChangeRequest $record): ?string => $record->note)
                    ->helperText($status === ChangeRequestStatus::Parked
                        ? __('Bijvoorbeeld wat er wordt uitgezocht en bij wie.')
                        : __('Bijvoorbeeld wat er in de planning is aangepast, of waarom het niet kan.'))
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (ChangeRequest $record, array $data) use ($status, $label): void {
                app(ChangeRequestService::class)->handle($record, $status, $data['note'] ?? null, auth()->id());
                Notification::make()->title(__(':action gelukt', ['action' => $label]))->success()->send();
            });
    }
}
