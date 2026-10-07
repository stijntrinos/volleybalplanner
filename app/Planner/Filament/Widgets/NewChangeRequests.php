<?php

namespace App\Planner\Filament\Widgets;

use App\Planner\Enums\ChangeRequestStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Concerns\HasPlannerWidget;
use App\Planner\Filament\Resources\ChangeRequests\ChangeRequestResource;
use App\Planner\Models\ChangeRequest;
use App\Planner\Support\DateFormat;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * The requests teams submit through the shared link. Only the open ones: a
 * handled request belongs in the resource, not on the dashboard.
 */
class NewChangeRequests extends TableWidget
{
    use HasPlannerWidget;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static function requiredFeatures(): array
    {
        return [PlannerFeature::ChangeRequests];
    }

    public function getHeading(): string
    {
        return __('Nieuwe wijzigingsverzoeken');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ChangeRequest::query()
                ->where('status', ChangeRequestStatus::Open)
                ->with(['team', 'match', 'hallDuty'])
                ->latest('created_at'))
            ->emptyStateHeading(__('Geen openstaande verzoeken'))
            ->emptyStateDescription(__('Aanvragen voor wedstrijden en FTZ-diensten komen hier binnen.'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Binnengekomen'))
                    ->formatStateUsing(fn ($state): string => DateFormat::dateTime($state) ?? '—'),

                TextColumn::make('team.code')->label(__('Team')),

                TextColumn::make('subject')
                    ->label(__('Onderwerp'))
                    ->state(fn (ChangeRequest $record): string => $record->subjectDescription())
                    ->description(fn (ChangeRequest $record): string => (string) ($record->isDuty()
                        ? collect([DateFormat::date($record->duty_date), $record->duty_time])->filter()->implode(' · ')
                        : (DateFormat::date($record->match?->date) ?? $record->match_code)))
                    ->wrap(),

                TextColumn::make('reason')
                    ->label(__('Reden'))
                    ->state(fn (ChangeRequest $record): string => $record->reasonText())
                    ->wrap(),

                TextColumn::make('name')->label(__('Aanvrager')),
            ])
            ->headerActions([
                Action::make('all')
                    ->label(__('Alle verzoeken'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (): string => ChangeRequestResource::getUrl('index')),
            ]);
    }
}
