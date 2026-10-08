<?php

namespace App\Filament\Platform\Resources\Plans\Tables;

use App\Models\Plan;
use App\Planner\Features\PlanLimit;
use App\Planner\Features\PlannerFeature;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        $limit = fn (PlanLimit $limit): TextColumn => TextColumn::make($limit->column())
            ->label($limit->getLabel())
            ->placeholder('Onbeperkt');

        return $table
            ->columns([
                TextColumn::make('name')->label('Naam')->weight('semibold')
                    ->description(fn (Plan $record): ?string => $record->tagline),
                TextColumn::make('price')->label('Prijs per seizoen')
                    ->state(fn (Plan $record): ?string => $record->formattedPrice())
                    ->placeholder('Op aanvraag'),
                $limit(PlanLimit::Teams),
                $limit(PlanLimit::Venues),
                $limit(PlanLimit::Admins),
                TextColumn::make('modules')->label('Modules')
                    ->state(fn (Plan $record): string => count($record->plannerFeatures()).' van '.count(PlannerFeature::cases())),
                TextColumn::make('clubs_count')->label('Clubs')->counts('clubs'),
                IconColumn::make('is_public')->label('Publiek')->tooltip('Op de prijzenpagina')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated(false)
            ->recordActions([EditAction::make()]);
    }
}
