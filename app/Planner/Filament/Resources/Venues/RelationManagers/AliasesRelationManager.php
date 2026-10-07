<?php

namespace App\Planner\Filament\Resources\Venues\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AliasesRelationManager extends RelationManager
{
    protected static string $relationship = 'aliases';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Importnamen');
    }

    public static function getModelLabel(): ?string
    {
        return __('importnaam');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('importnamen');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('Alternatieve naam'))
                ->placeholder(__('Bijv. De Wiel'))
                ->helperText(__('Deze naam wordt tijdens imports automatisch aan deze sporthal gekoppeld.'))
                ->required()
                ->maxLength(255)
                // Unique within the club (global scope), across all its venues.
                ->scopedUnique(ignoreRecord: true)
                ->validationMessages([
                    'unique' => __('Deze importnaam is al aan een sporthal gekoppeld.'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Alternatieve naam'))->searchable()->sortable(),
            ])
            ->headerActions([CreateAction::make()->label(__('Importnaam toevoegen'))])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('name')
            ->emptyStateHeading(__('Nog geen alternatieve importnamen'))
            ->emptyStateDescription(__('Voeg schrijfwijzen toe die in Nevobo- of trainingsimports voorkomen.'))
            ->emptyStateIcon('heroicon-o-arrows-right-left');
    }
}
