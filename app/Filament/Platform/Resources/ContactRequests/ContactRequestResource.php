<?php

namespace App\Filament\Platform\Resources\ContactRequests;

use App\Enums\ContactTopic;
use App\Filament\Platform\Resources\ContactRequests\Pages\ListContactRequests;
use App\Models\ContactRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
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
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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

    public static function getPages(): array
    {
        return [
            'index' => ListContactRequests::route('/'),
        ];
    }
}
