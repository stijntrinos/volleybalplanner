<?php

namespace App\Planner\Filament\Resources\ChangeRequests\Schemas;

use App\Planner\Enums\ChangeRequestKind;
use App\Planner\Enums\ContactPreference;
use App\Planner\Enums\DutyType;
use App\Planner\Models\ChangeRequest;
use App\Planner\Support\DateFormat;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Everything known about a request in one place. The explanation and the
 * handling do not fit in the table, while you need them to decide.
 */
class ChangeRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Verzoek'))
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->iconColor('primary')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(__('Binnengekomen'))
                            ->state(fn (ChangeRequest $record): string => DateFormat::dateTime($record->created_at) ?? '—'),
                        TextEntry::make('team.code')->label(__('Team'))->placeholder(__('Onbekend')),
                        TextEntry::make('kind')
                            ->label(__('Soort'))
                            ->badge()
                            ->color(fn (?ChangeRequestKind $state): string => $state === ChangeRequestKind::Duty ? 'info' : 'gray'),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge(),
                    ])
                    ->columnSpanFull(),
                Section::make(__('Wedstrijd'))
                    ->icon('heroicon-o-calendar-days')
                    ->iconColor('primary')
                    ->columns(2)
                    ->visible(fn (ChangeRequest $record): bool => !$record->isDuty())
                    ->schema([
                        TextEntry::make('match_description')
                            ->label(__('Wedstrijd'))
                            ->state(fn (ChangeRequest $record): string => $record->matchDescription())
                            ->weight('semibold'),
                        TextEntry::make('match_code')->label(__('Wedstrijdcode'))->placeholder(__('Onbekend')),
                        TextEntry::make('match.date')
                            ->label(__('Speeldatum'))
                            ->state(fn (ChangeRequest $record): string => trim(collect([
                                DateFormat::date($record->match?->date),
                                DateFormat::time($record->match?->time),
                            ])->filter()->implode(' · ')))
                            ->placeholder(__('Niet meer bekend')),
                        TextEntry::make('match.venue.name')->label(__('Sporthal'))->placeholder(__('Onbekend')),
                        TextEntry::make('proposed_date')
                            ->label(__('Voorgestelde nieuwe datum'))
                            ->state(fn (ChangeRequest $record): ?string => $record->proposalText())
                            ->placeholder(__('Geen voorstel gedaan'))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make(__('FTZ-dienst'))
                    ->icon('heroicon-o-hand-raised')
                    ->iconColor('primary')
                    ->columns(2)
                    ->visible(fn (ChangeRequest $record): bool => $record->isDuty())
                    ->schema([
                        TextEntry::make('duty_type')
                            ->label(__('Dienst'))
                            ->formatStateUsing(fn (?DutyType $state): string => $state?->getLabel() ?? __('FTZ-dienst')),
                        TextEntry::make('duty_date')
                            ->label(__('Datum en tijd'))
                            ->state(fn (ChangeRequest $record): string => trim(collect([
                                DateFormat::date($record->duty_date),
                                DateFormat::time($record->duty_time),
                            ])->filter()->implode(' · ')))
                            ->placeholder(__('Onbekend')),
                        TextEntry::make('duty_location')->label(__('Locatie'))->placeholder(__('Onbekend')),
                        TextEntry::make('duty_description')->label(__('Omschrijving'))->placeholder(__('Geen omschrijving')),
                    ])
                    ->columnSpanFull(),
                Section::make(__('Aanvrager'))
                    ->icon('heroicon-o-user')
                    ->iconColor('primary')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label(__('Naam'))->placeholder(__('Niet ingevuld')),
                        TextEntry::make('email')->label(__('E-mailadres'))->copyable()->placeholder(__('Niet ingevuld')),
                        TextEntry::make('phone')->label(__('Mobiel nummer'))->copyable()->placeholder(__('Niet ingevuld')),
                        TextEntry::make('contact_preference')
                            ->label(__('Contact via'))
                            ->badge()
                            ->state(fn (ChangeRequest $record): ContactPreference => $record->contact_preference ?? ContactPreference::Email)
                            ->color(fn (?ContactPreference $state): string => $state === ContactPreference::Whatsapp ? 'success' : 'gray'),
                    ])
                    ->columnSpanFull(),
                Section::make(__('Reden'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->iconColor('primary')
                    ->schema([
                        TextEntry::make('reason')
                            ->label(__('Reden'))
                            ->state(fn (ChangeRequest $record): string => $record->reasonText()),
                        TextEntry::make('explanation')
                            ->label(__('Toelichting'))
                            ->placeholder(__('Geen toelichting meegegeven')),
                    ])
                    ->columnSpanFull(),
                Section::make(__('Afhandeling'))
                    ->icon('heroicon-o-check-circle')
                    ->iconColor('gray')
                    ->columns(2)
                    ->visible(fn (ChangeRequest $record): bool => !$record->isOpen() || filled($record->note))
                    ->schema([
                        TextEntry::make('handled_at')
                            ->label(__('Behandeld op'))
                            ->state(fn (ChangeRequest $record): string => DateFormat::dateTime($record->handled_at) ?? '—'),
                        TextEntry::make('handledBy.name')->label(__('Behandeld door'))->placeholder(__('Onbekend')),
                        TextEntry::make('note')
                            ->label(__('Notitie voor wedstrijdzaken'))
                            ->placeholder(__('Geen notitie'))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
