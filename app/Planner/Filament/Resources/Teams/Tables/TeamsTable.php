<?php

namespace App\Planner\Filament\Resources\Teams\Tables;

use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Resources\Teams\Schemas\TeamForm;
use App\Planner\Models\Team;
use App\Planner\Services\Duties\PointsBalance;
use App\Planner\Services\DutyTallyService;
use App\Planner\Support\TeamCode;
use App\Support\Tenancy\CurrentClub;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TeamsTable
{
    public static function configure(Table $table): Table
    {
        $club = app(CurrentClub::class)->get();
        $volunteerPoints = (bool) $club?->hasFeature(PlannerFeature::VolunteerPoints);
        $lockerRooms = (bool) $club?->hasFeature(PlannerFeature::LockerRooms);

        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('Team'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->description(fn (Team $record): string => $record->name ?: __('Geen naam')),

                TextColumn::make('default_planning')
                    ->label(__('Standaardplanning'))
                    ->state(fn (Team $record): string => collect([
                        $record->default_weekday,
                        $record->default_time ? substr($record->default_time, 0, 5) : null,
                    ])->filter()->implode(' · ') ?: __('Niet compleet'))
                    ->description(fn (Team $record): string => collect([
                        $record->defaultVenue?->name,
                        $record->default_court ? __('veld :court', ['court' => $record->default_court]) : null,
                    ])->filter()->implode(' · ') ?: __('Geen standaardlocatie'))
                    ->color(fn (Team $record): string => $record->default_weekday && $record->default_time && $record->default_venue_id ? 'gray' : 'warning'),

                ViewColumn::make('traits')
                    ->label(__('Inzet & automatisering'))
                    ->view('planner::filament.tables.columns.team-traits'),

                // The minimum is the yardstick; the need only shows on the
                // Standing tab. A team without a minimum shows no standing.
                TextColumn::make('minimum')
                    ->label(__('Minimum'))
                    ->visible($volunteerPoints)
                    ->state(fn (Team $record): string => self::balance($record)->hasMinimum() ? (string) self::balance($record)->minimum() : __('Geen minimum'))
                    ->color(fn (Team $record): ?string => self::balance($record)->hasMinimum() ? null : 'gray')
                    ->alignEnd(),
                TextColumn::make('total_earned')
                    ->label(__('Totaal geleverd'))
                    ->visible($volunteerPoints)
                    ->state(fn (Team $record): int => self::balance($record)->total())
                    ->description(fn (Team $record): string => __(':earned geleverd · :planned ingepland', [
                        'earned' => self::balance($record)->earned,
                        'planned' => self::balance($record)->planned,
                    ]))
                    ->alignEnd(),
                TextColumn::make('remaining')
                    ->label(__('Nog in te vullen'))
                    ->visible($volunteerPoints)
                    ->state(fn (Team $record): string => self::remaining(self::balance($record)))
                    ->description(fn (Team $record): ?string => self::balance($record)->isAboveTarget() ? __('Boven doel') : null)
                    ->color(fn (Team $record): ?string => match (true) {
                        self::balance($record)->isBelowMinimum() => 'warning',
                        self::balance($record)->isAboveTarget() => 'success',
                        default => null,
                    })
                    ->weight(FontWeight::SemiBold)
                    ->alignEnd(),
            ])
            ->filters(array_values(array_filter([
                $lockerRooms ? SelectFilter::make('locker_room_category')
                    ->label(__('Kleedkamercategorie'))
                    ->options(TeamForm::lockerRoomOptions()) : null,
                SelectFilter::make('default_venue_id')
                    ->label(__('Standaard sporthal'))
                    ->relationship('defaultVenue', 'name')
                    ->searchable()
                    ->preload(),
            ])))
            ->modifyQueryUsing(fn ($query) => $query->with('defaultVenue'))
            ->recordActions([
                EditAction::make()->label(__('Beheren')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code')
            ->emptyStateHeading(__('Nog geen teams'))
            ->emptyStateDescription(__('Voeg teams toe of importeer een wedstrijdplanning om te beginnen.'))
            ->emptyStateIcon('heroicon-o-user-group');
    }

    private static function balance(Team $record): PointsBalance
    {
        // Once per request, not per row.
        $planned = once(fn (): array => ($version = DutyTallyService::displayableVersion())
            ? app(DutyTallyService::class)->plannedPerTeam($version)
            : []);

        return PointsBalance::forTeam($record, $planned[TeamCode::key($record->code)]['total'] ?? 0);
    }

    /** The shortfall to the minimum, a negative number above it and nothing without a minimum. */
    private static function remaining(PointsBalance $balance): string
    {
        return match (true) {
            !$balance->hasMinimum() => '',
            $balance->isAboveTarget() => '-'.$balance->aboveMinimum(),
            default => (string) $balance->remaining(),
        };
    }
}
