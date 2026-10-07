<?php

namespace App\Planner\Filament\Resources\Venues\Pages;

use App\Planner\Filament\Resources\Venues\RelationManagers\AliasesRelationManager;
use App\Planner\Filament\Resources\Venues\RelationManagers\CourtsRelationManager;
use App\Planner\Filament\Resources\Venues\VenueResource;
use App\Planner\Models\Venue;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use LogicException;
use Override;

class EditVenue extends EditRecord
{
    protected static string $resource = VenueResource::class;

    #[Override]
    public function getRecord(): Venue
    {
        $record = parent::getRecord();

        if (!$record instanceof Venue) {
            throw new LogicException('The opened record is not a venue.');
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** Courts and import names side by side as tabs instead of two stacked tables. */
    public function getRelationManagersContentComponent(): Component
    {
        $data = [
            'ownerRecord' => $this->getRecord(),
            'pageClass' => static::class,
        ];

        return Group::make([
            Tabs::make(__('Sporthalinrichting'))->persistTabInQueryString('setup')->tabs([
                Tab::make(__('Velden'))->icon(Heroicon::OutlinedRectangleGroup)
                    ->badge($this->getRecord()->courts()->count())
                    ->schema([Livewire::make(CourtsRelationManager::class, $data)->key(CourtsRelationManager::class)]),
                Tab::make(__('Importnamen'))->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->badge($this->getRecord()->aliases()->count())
                    ->schema([Livewire::make(AliasesRelationManager::class, $data)->key(AliasesRelationManager::class)]),
            ]),
        ]);
    }
}
