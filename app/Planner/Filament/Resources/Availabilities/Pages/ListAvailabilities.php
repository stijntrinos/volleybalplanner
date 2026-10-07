<?php

namespace App\Planner\Filament\Resources\Availabilities\Pages;

use App\Planner\Enums\AvailabilityScope;
use App\Planner\Filament\Pages\Concerns\ShowsErrors;
use App\Planner\Filament\Resources\Availabilities\AvailabilityResource;
use App\Planner\Models\Availability;
use App\Planner\Services\AvailabilityImportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class ListAvailabilities extends ListRecords
{
    use ShowsErrors;

    protected static string $resource = AvailabilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label(__('Importeren'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->visible(fn (): bool => AvailabilityResource::canCreate())
                ->modalHeading(__('Beschikbaarheid importeren'))
                ->modalDescription(__('Kolommen: sporthal, scope, datum, weekdag, periode van, periode tot, begintijd, eindtijd, status, veld en notitie. Een bestaande regel voor dezelfde zaal en periode wordt bijgewerkt.'))
                ->modalSubmitActionLabel(__('Importeren'))
                ->schema([
                    FileUpload::make('file')
                        ->label(__('Bestand'))
                        ->acceptedFileTypes([
                            'text/csv', 'application/csv', 'text/plain',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $file = $data['file'];
                    $path = $file instanceof TemporaryUploadedFile ? $file->getRealPath() : (string) $file;

                    try {
                        $result = app(AvailabilityImportService::class)->import($path);
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->showError(__('Import mislukt'), $exception->getMessage());

                        return;
                    }

                    $message = __(':created toegevoegd, :updated bijgewerkt', ['created' => $result['created'], 'updated' => $result['updated']]);

                    if ($result['errors'] !== []) {
                        $lines = implode("\n", array_map(
                            fn (array $error): string => __('Rij :row: :reason', ['row' => $error['row'], 'reason' => $error['reason']]),
                            array_slice($result['errors'], 0, 5),
                        ));

                        Notification::make()
                            ->title($message.', '.__(':count regels overgeslagen', ['count' => count($result['errors'])]))
                            ->body($lines)
                            ->warning()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()->title($message)->success()->send();
                }),
            CreateAction::make()
                ->label(__('Beschikbaarheid toevoegen'))
                ->icon(Heroicon::OutlinedPlus),
        ];
    }

    public function getSubheading(): ?string
    {
        return __('Leg vaste openingstijden en tijdelijke uitzonderingen vast per sporthal of veld.');
    }

    public function getTabs(): array
    {
        $current = fn (Builder $query): Builder => $query->where(function (Builder $query): void {
            $query->where('scope', AvailabilityScope::Weekday)
                ->orWhere(fn (Builder $query): Builder => $query
                    ->where('scope', AvailabilityScope::Date)
                    ->whereDate('date', '>=', today()))
                ->orWhere(fn (Builder $query): Builder => $query
                    ->where('scope', AvailabilityScope::Period)
                    ->whereDate('period_end', '>=', today()));
        });

        $past = fn (Builder $query): Builder => $query->where(function (Builder $query): void {
            $query->where(fn (Builder $query): Builder => $query
                ->where('scope', AvailabilityScope::Date)
                ->whereDate('date', '<', today()))
                ->orWhere(fn (Builder $query): Builder => $query
                    ->where('scope', AvailabilityScope::Period)
                    ->whereDate('period_end', '<', today()));
        });

        $exceptions = [AvailabilityScope::Date, AvailabilityScope::Period];

        return [
            'current' => Tab::make(__('Actueel & aankomend'))
                ->icon(Heroicon::OutlinedClock)
                ->badge($current(Availability::query())->count())
                ->modifyQueryUsing($current),
            'weekly' => Tab::make(__('Wekelijkse regels'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->badge(Availability::query()->where('scope', AvailabilityScope::Weekday)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('scope', AvailabilityScope::Weekday)),
            'exceptions' => Tab::make(__('Uitzonderingen'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->badge(Availability::query()->whereIn('scope', $exceptions)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('scope', $exceptions)),
            'past' => Tab::make(__('Verlopen'))
                ->icon(Heroicon::OutlinedArchiveBox)
                ->badge($past(Availability::query())->count())
                ->modifyQueryUsing($past),
            'all' => Tab::make(__('Alles'))
                ->badge(Availability::query()->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'current';
    }
}
