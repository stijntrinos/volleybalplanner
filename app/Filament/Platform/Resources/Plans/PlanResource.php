<?php

namespace App\Filament\Platform\Resources\Plans;

use App\Filament\Platform\Resources\Plans\Pages\CreatePlan;
use App\Filament\Platform\Resources\Plans\Pages\EditPlan;
use App\Filament\Platform\Resources\Plans\Pages\ListPlans;
use App\Filament\Platform\Resources\Plans\Schemas\PlanForm;
use App\Filament\Platform\Resources\Plans\Tables\PlansTable;
use App\Models\Plan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The subscriptions: what each plan contains, what it costs and how it shows
 * on the pricing page. Clubs pick one in their club form.
 */
class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $slug = 'abonnementen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Abonnementen';

    protected static ?string $modelLabel = 'abonnement';

    protected static ?string $pluralModelLabel = 'abonnementen';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return PlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
