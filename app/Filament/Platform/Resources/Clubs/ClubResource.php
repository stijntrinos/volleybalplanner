<?php

namespace App\Filament\Platform\Resources\Clubs;

use App\Filament\Platform\Resources\Clubs\Pages\CreateClub;
use App\Filament\Platform\Resources\Clubs\Pages\EditClub;
use App\Filament\Platform\Resources\Clubs\Pages\ListClubs;
use App\Filament\Platform\Resources\Clubs\RelationManagers\UsersRelationManager;
use App\Filament\Platform\Resources\Clubs\Schemas\ClubForm;
use App\Filament\Platform\Resources\Clubs\Tables\ClubsTable;
use App\Models\Club;
use App\Planner\Filament\Support\NevoboLinkForm;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClubResource extends Resource
{
    protected static ?string $model = Club::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'club';

    protected static ?string $pluralModelLabel = 'clubs';

    public static function form(Schema $schema): Schema
    {
        return ClubForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClubsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubs::route('/'),
            'create' => CreateClub::route('/create'),
            'edit' => EditClub::route('/{record}/edit'),
        ];
    }

    /**
     * Opens the club's workspace; platform admins can enter every club.
     */
    public static function openClubAction(): Action
    {
        return Action::make('openClub')
            ->label('Naar club')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->url(fn (Club $record): string => Filament::getPanel('club')->getUrl($record))
            ->openUrlInNewTab();
    }

    /**
     * A demo that became a customer: it is no longer pruned. Name, slug,
     * plan and subscription are then edited as for any club.
     */
    public static function convertDemoAction(): Action
    {
        return Action::make('convertDemo')
            ->label(__('Omzetten naar klant'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Club $record): bool => $record->is_demo)
            ->requiresConfirmation()
            ->modalDescription(__('De club is dan geen demo meer en wordt niet automatisch verwijderd. Pas daarna naam, slug, pakket en abonnement aan.'))
            ->action(function (Club $record): void {
                $record->update(['is_demo' => false]);
                Notification::make()->title(__('Omgezet naar klant'))->success()->send();
            });
    }

    /**
     * Sets up a new customer's teams and Nevobo feeds in one go; the same
     * flow as the club's own "Nevobo-koppeling" page.
     */
    public static function linkNevoboAction(): Action
    {
        return Action::make('linkNevobo')
            ->label(__('Koppel aan Nevobo'))
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->modalHeading(fn (Club $record): string => __('Koppel :club aan de Nevobo', ['club' => $record->name]))
            ->modalDescription(__('Zoek de vereniging, kies de teams en de planner maakt teams en feeds aan in deze club.'))
            ->modalSubmitActionLabel(__('Koppelen'))
            ->modalWidth(Width::FiveExtraLarge)
            ->fillForm(fn (Club $record): array => NevoboLinkForm::initialState($record))
            ->schema(fn (Club $record): array => NevoboLinkForm::components(fn (): Club => $record))
            ->action(function (Club $record, array $data, Action $action): void {
                if (NevoboLinkForm::submit($record, $data) === null) {
                    $action->halt();
                }
            });
    }
}
