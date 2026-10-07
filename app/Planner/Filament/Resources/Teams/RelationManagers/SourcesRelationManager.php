<?php

namespace App\Planner\Filament\Resources\Teams\RelationManagers;

use App\Planner\Enums\TeamSourceType;
use App\Planner\Models\TeamSource;
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
use Illuminate\Database\Eloquent\Model;

class SourcesRelationManager extends RelationManager
{
    protected static string $relationship = 'sources';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Externe wedstrijdbronnen');
    }

    public static function getModelLabel(): ?string
    {
        return __('wedstrijdbron');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('wedstrijdbronnen');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label(__('Naam'))->placeholder(__('Bijv. Competitie'))->required()->maxLength(255),
            Select::make('type')
                ->label(__('Soort feed'))
                ->options(TeamSourceType::class)
                ->default(TeamSourceType::Schedule->value)
                ->native(false)
                ->required()
                // The match import only reads schedule feeds; standings hold no
                // matches and would show up as an empty schedule.
                ->helperText(__('Alleen een programmafeed wordt in de planning ingelezen.')),
            Toggle::make('is_active')->label(__('Actief'))->default(true),
            TextInput::make('url')->label(__('RSS URL'))->url()->required()->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Naam'))->weight('semibold')->searchable(),
                TextColumn::make('type')
                    ->label(__('Soort'))
                    ->badge()
                    ->color(fn (?TeamSourceType $state): string => $state === TeamSourceType::Schedule ? 'primary' : 'gray'),
                TextColumn::make('url')->label(__('RSS URL'))->limit(80)->copyable(),
                IconColumn::make('is_active')->label(__('Actief'))->boolean(),
                TextColumn::make('last_succeeded_at')->label(__('Laatst geslaagd'))->dateTime('d-m-Y H:i')->placeholder(__('Nog niet getest')),
                // Without this column a broken nightly sync would only be visible in the logs.
                TextColumn::make('last_error')
                    ->label(__('Laatste fout'))
                    ->placeholder(__('Geen'))
                    ->color('danger')
                    ->wrap()
                    ->limit(120)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->description(fn (TeamSource $record): ?string => $record->last_checked_at
                        ? __('Gecontroleerd op :moment', ['moment' => $record->last_checked_at->format('d-m-Y H:i')])
                        : null),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->headerActions([CreateAction::make()->label(__('Wedstrijdbron toevoegen'))])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('Nog geen externe wedstrijdbronnen'))
            ->emptyStateDescription(__('Voeg een Nevobo RSS-feed toe om uitwedstrijden en beschikbaarheid voor dit team bij te houden.'))
            ->emptyStateIcon('heroicon-o-rss');
    }
}
