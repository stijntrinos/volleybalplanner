<?php

namespace App\Planner\Filament\Resources\Venues\RelationManagers;

use App\Planner\Enums\Source;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CourtsRelationManager extends RelationManager
{
    protected static string $relationship = 'courts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Velden');
    }

    public static function getModelLabel(): ?string
    {
        return __('veld');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('velden');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')
                ->label(__('Naam'))
                ->required()
                ->maxLength(50)
                ->scopedUnique(
                    ignoreRecord: true,
                    modifyQueryUsing: fn (Builder $query): Builder => $query->where('venue_id', $this->getOwnerRecord()->getKey()),
                )
                ->validationMessages([
                    'unique' => __('Deze sporthal heeft al een veld met deze naam.'),
                ])
                ->placeholder(__('Bijv. 1 of C4'))
                ->helperText(__('De veldnaam zoals deze in de planning wordt getoond.')),

            TextInput::make('sort_order')
                ->label(__('Volgorde'))
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->helperText(__('Bepaalt de positie van het veld bij Thuiswedstrijden.')),

            Select::make('source')
                ->label(__('Bron'))
                ->options([
                    Source::Manual->value => __('Handmatig'),
                    Source::MatchImport->value => __('Wedstrijdimport'),
                ])
                ->required()
                ->native(false)
                ->default(Source::Manual->value),

            Toggle::make('is_training_court')
                ->label(__('Trainingsveld'))
                ->helperText(__('Dit veld wordt alleen voor trainingen gebruikt.'))
                ->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Naam'))
                    ->sortable(),

                TextColumn::make('source')
                    ->label(__('Bron'))
                    ->formatStateUsing(fn (?Source $state): string => $state === Source::MatchImport ? __('Wedstrijdimport') : __('Handmatig')),

                IconColumn::make('is_training_court')
                    ->label(__('Trainingsveld'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label(__('Veld toevoegen')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('Nog geen velden ingericht'))
            ->emptyStateDescription(__('Voeg de speel- en trainingsvelden van deze sporthal toe.'))
            ->emptyStateIcon('heroicon-o-rectangle-group');
    }
}
