<?php

namespace App\Planner\Filament\Resources\Teams\RelationManagers;

use App\Planner\Models\TeamAbsence;
use App\Planner\Support\DateFormat;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The days a team has reported itself absent. The duty planning does not
 * propose the team then; the planning committee can still choose it.
 */
class AbsencesRelationManager extends RelationManager
{
    protected static string $relationship = 'absences';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Afwezigheid');
    }

    public static function getModelLabel(): ?string
    {
        return __('afwezigheid');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('afwezigheden');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('date_from')->label(__('Vanaf'))->native(false)->displayFormat('d-m-Y')->required(),
            DatePicker::make('date_until')
                ->label(__('Tot en met'))
                ->native(false)
                ->displayFormat('d-m-Y')
                ->afterOrEqual('date_from')
                ->helperText(__('Leeg laten voor een enkele dag.')),
            TimePicker::make('start_time')->label(__('Van'))->seconds(false)->helperText(__('Leeg laten voor de hele dag.')),
            TimePicker::make('end_time')->label(__('Tot'))->seconds(false)->after('start_time')->requiredWith('start_time'),
            TextInput::make('reason')->label(__('Reden'))->placeholder(__('Bijv. toernooi of kamp'))->maxLength(255)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date_from')->label(__('Vanaf'))->date(DateFormat::DATE)->sortable(),
                TextColumn::make('date_until')->label(__('Tot en met'))->date(DateFormat::DATE)->placeholder(__('Zelfde dag')),
                TextColumn::make('times')
                    ->label(__('Tijd'))
                    ->state(fn (TeamAbsence $record): string => filled($record->start_time) && filled($record->end_time)
                        ? substr((string) $record->start_time, 0, 5).' - '.substr((string) $record->end_time, 0, 5)
                        : __('Hele dag')),
                TextColumn::make('reason')->label(__('Reden'))->placeholder('-')->wrap(),
            ])
            ->defaultSort('date_from')
            // club_id is filled by BelongsToClub.
            ->headerActions([CreateAction::make()->label(__('Afwezigheid toevoegen'))])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('Geen afwezigheid opgegeven'))
            ->emptyStateDescription(__('Geef hier dagen op waarop het team geen diensten kan doen. De FTZ-planning stelt het team dan niet voor.'))
            ->emptyStateIcon('heroicon-o-calendar-days');
    }
}
