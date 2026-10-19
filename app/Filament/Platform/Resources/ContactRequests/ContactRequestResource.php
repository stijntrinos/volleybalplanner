<?php

namespace App\Filament\Platform\Resources\ContactRequests;

use App\Enums\ContactTopic;
use App\Filament\Platform\Resources\ContactRequests\Pages\ListContactRequests;
use App\Models\Club;
use App\Models\ContactRequest;
use App\Models\User;
use App\Support\Demo\DemoClubs;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Demo, question and quote requests from the contact form of the public site.
 */
class ContactRequestResource extends Resource
{
    protected static ?string $model = ContactRequest::class;

    protected static ?string $slug = 'contact-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $modelLabel = 'aanvraag';

    protected static ?string $pluralModelLabel = 'aanvragen';

    protected static ?string $navigationLabel = 'Aanvragen';

    protected static ?int $navigationSort = 20;

    public static function getNavigationBadge(): ?string
    {
        $open = ContactRequest::query()->open()->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('topic')->label('Onderwerp')->badge(),
            TextEntry::make('created_at')->label('Binnengekomen')->dateTime('d-m-Y H:i'),
            TextEntry::make('name')->label('Naam'),
            TextEntry::make('club')->label('Vereniging'),
            TextEntry::make('email')->label('E-mailadres')->url(fn (ContactRequest $record): string => 'mailto:'.$record->email),
            TextEntry::make('phone')->label('Telefoon')->placeholder('—'),
            TextEntry::make('teams')->label('Aantal teams')->placeholder('—'),
            TextEntry::make('venues')->label('Aantal sporthallen')->placeholder('—'),
            TextEntry::make('message')->label('Bericht')->placeholder('Geen bericht')->columnSpanFull(),
            TextEntry::make('handled_at')->label('Afgehandeld')->dateTime('d-m-Y H:i')->placeholder('Nog niet'),
            TextEntry::make('demoClub.name')
                ->label(__('Democlub'))
                ->visible(fn (ContactRequest $record): bool => $record->demoClub !== null)
                ->url(fn (ContactRequest $record): ?string => $record->demoClub ? self::clubUrl($record->demoClub) : null, shouldOpenInNewTab: true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('demoClub'))
            ->columns([
                TextColumn::make('created_at')->label('Binnengekomen')->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('topic')->label('Onderwerp')->badge(),
                TextColumn::make('club')->label('Vereniging')->searchable()->description(fn (ContactRequest $record): string => $record->name),
                TextColumn::make('email')->label('E-mailadres')->searchable()->copyable(),
                TextColumn::make('teams')->label('Teams')->placeholder('—'),
                TextColumn::make('handled_at')
                    ->label('Status')
                    ->badge()
                    ->state(fn (ContactRequest $record): string => $record->handled_at ? 'Afgehandeld' : 'Open')
                    ->color(fn (ContactRequest $record): string => $record->handled_at ? 'gray' : 'warning'),
            ])
            ->filters([
                TernaryFilter::make('handled')
                    ->label('Status')
                    ->placeholder('Alle aanvragen')
                    ->trueLabel('Afgehandeld')
                    ->falseLabel('Open')
                    ->default(false)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('handled_at'),
                        false: fn (Builder $query) => $query->whereNull('handled_at'),
                    ),
                SelectFilter::make('topic')->label('Onderwerp')->options(ContactTopic::class),
            ])
            ->recordActions([
                ViewAction::make(),
                self::createDemoAction(),
                self::openDemoAction(),
                Action::make('handle')
                    ->label('Afgehandeld')
                    ->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (ContactRequest $record): bool => $record->handled_at === null)
                    ->action(function (ContactRequest $record): void {
                        $record->forceFill(['handled_at' => now()])->save();
                        Notification::make()->title('Aanvraag afgehandeld')->success()->send();
                    }),
                Action::make('reopen')
                    ->label('Heropenen')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn (ContactRequest $record): bool => $record->handled_at !== null)
                    ->action(fn (ContactRequest $record) => $record->forceFill(['handled_at' => null])->save()),
            ])
            ->emptyStateHeading('Geen aanvragen')
            ->emptyStateDescription('Demo- en contactaanvragen van de website komen hier binnen.');
    }

    /** Makes a demo club for a demo request (DemoClubs) and invites the requester. */
    public static function createDemoAction(): Action
    {
        return Action::make('createDemo')
            ->label(__('Demo aanmaken'))
            ->icon(Heroicon::OutlinedSparkles)
            ->color('primary')
            ->visible(fn (ContactRequest $record): bool => $record->isDemoRequest() && $record->demo_club_id === null)
            ->requiresConfirmation()
            ->modalHeading(__('Demo aanmaken'))
            ->modalDescription(fn (ContactRequest $record): string => __(
                'Er wordt een democlub ":club" aangemaakt op het grootste pakket, met voorbeelddata en een proefperiode van :days dagen. :email krijgt een uitnodiging als clubbeheerder en de aanvraag wordt afgehandeld. Na de proefperiode staat de demo in leesstand; :prune dagen later wordt hij verwijderd, tenzij je hem omzet naar klant.',
                [
                    'club' => __(':name (demo)', ['name' => trim($record->club) !== '' ? trim($record->club) : $record->name]),
                    'days' => (int) config('planner.demo.trial_days', 14),
                    'email' => $record->email,
                    'prune' => (int) config('planner.demo.prune_after_days', 30),
                ],
            ))
            ->modalSubmitActionLabel(__('Demo aanmaken'))
            ->action(function (ContactRequest $record): void {
                $user = auth()->user();
                $club = app(DemoClubs::class)->createFor($record, $user instanceof User ? $user : null);

                Notification::make()
                    ->title(__('Demo aangemaakt'))
                    ->body(__(':club staat klaar; de uitnodiging is verstuurd naar :email.', ['club' => $club->name, 'email' => $record->email]))
                    ->success()
                    ->send();
            });
    }

    /** Opens the demo club made from the request. */
    public static function openDemoAction(): Action
    {
        return Action::make('openDemo')
            ->label(__('Naar demo'))
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->visible(fn (ContactRequest $record): bool => $record->demoClub !== null)
            ->url(fn (ContactRequest $record): ?string => $record->demoClub ? self::clubUrl($record->demoClub) : null)
            ->openUrlInNewTab();
    }

    private static function clubUrl(Club $club): string
    {
        return Filament::getPanel('club')->getUrl($club);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactRequests::route('/'),
        ];
    }
}
