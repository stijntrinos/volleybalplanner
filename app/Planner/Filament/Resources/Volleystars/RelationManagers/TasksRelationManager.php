<?php

namespace App\Planner\Filament\Resources\Volleystars\RelationManagers;

use App\Planner\Enums\VolleystarsRole;
use App\Planner\Models\Court;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Models\VolleystarsTask;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Override;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Taken en teams');
    }

    public static function getModelLabel(): ?string
    {
        return __('taak');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('taken');
    }

    #[Override]
    public function getOwnerRecord(): VolleystarsBlock
    {
        $record = parent::getOwnerRecord();

        if (!$record instanceof VolleystarsBlock) {
            throw new LogicException('The owner of these tasks is not a VolleyStars block.');
        }

        return $record;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('role')->label(__('Taak'))->options(VolleystarsRole::class)->required()->native(false),
            Select::make('team_id')->label(__('Team'))->relationship('team', 'name')
                ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->code} · {$record->name}")
                ->searchable(['code', 'name'])->preload(),
            Select::make('court_id')->label(__('Veld'))
                ->options(fn () => Court::query()->where('venue_id', $this->getOwnerRecord()->venue_id)->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                ->placeholder(__('Alle velden'))->searchable()->preload()->native(false),
            TextInput::make('sort_order')->label(__('Volgorde'))->numeric()->minValue(0)->default(0)->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('role')->label(__('Taak'))->badge()
                ->color(fn (?VolleystarsRole $state): string => match ($state) {
                    VolleystarsRole::Setup => 'info',
                    VolleystarsRole::Scoring => 'success',
                    VolleystarsRole::Teardown => 'warning',
                    default => 'gray',
                }),
            TextColumn::make('team.code')->label(__('Verantwoordelijk team'))->placeholder(__('Nog niet toegewezen'))->weight('semibold')
                ->description(fn (VolleystarsTask $record): ?string => $record->team?->name),
            TextColumn::make('court.name')->label(__('Veld'))->placeholder(__('Alle velden')),
            TextColumn::make('sort_order')->label(__('Volgorde'))->sortable(),
        ])
            // club_id is filled by BelongsToClub.
            ->headerActions([CreateAction::make()->label(__('Taak toevoegen'))])
            ->recordActions([EditAction::make(), DeleteAction::make()])->defaultSort('sort_order')
            ->emptyStateHeading(__('Nog geen taken verdeeld'))
            ->emptyStateDescription(__('Voeg opbouw-, tel- en afbouwtaken toe en wijs ze toe aan teams.'))
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }
}
