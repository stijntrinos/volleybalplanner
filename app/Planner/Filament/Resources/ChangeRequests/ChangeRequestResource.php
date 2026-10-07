<?php

namespace App\Planner\Filament\Resources\ChangeRequests;

use App\Planner\Enums\ChangeRequestStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Concerns\HasPlannerNavigation;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Resources\ChangeRequests\Pages\ListChangeRequests;
use App\Planner\Filament\Resources\ChangeRequests\Tables\ChangeRequestsTable;
use App\Planner\Models\ChangeRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

class ChangeRequestResource extends Resource
{
    use HasPlannerNavigation;

    protected static ?string $model = ChangeRequest::class;

    protected static ?string $slug = 'change-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::INPUT;

    protected static ?int $navigationSort = 14;

    protected static function requiredFeatures(): array
    {
        return [PlannerFeature::ChangeRequests];
    }

    public static function getNavigationLabel(): string
    {
        return __('Wijzigingsverzoeken');
    }

    public static function getModelLabel(): string
    {
        return __('Wijzigingsverzoek');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Wijzigingsverzoeken');
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return ChangeRequestsTable::configure($table);
    }

    /** The number of open requests in the navigation, so nobody misses them. */
    public static function getNavigationBadge(): ?string
    {
        $count = ChangeRequest::query()->where('status', ChangeRequestStatus::Open)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        // Requests come in through the public form only.
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChangeRequests::route('/'),
        ];
    }
}
