<?php

namespace App\Filament\Platform\Resources\Clubs\Schemas;

use App\Models\Plan;
use App\Planner\Features\PlanLimit;
use App\Planner\Features\PlannerFeature;
use App\Support\Tenancy\ReservedSlugs;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ClubForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Vereniging')
                    ->description('Naam en adres van de werkruimte. De slug komt terug in de publieke programma- en verzoekpagina’s.')
                    ->icon('heroicon-o-building-office-2')
                    ->aside()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Naam')
                            ->placeholder('VC Voorbeeld')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                if (blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            })
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->prefix(Str::after(url('/'), '://').'/')
                            ->helperText('Alleen kleine letters, cijfers en streepjes.')
                            ->required()
                            ->maxLength(64)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->notIn(ReservedSlugs::ALL)
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),
                        Toggle::make('require_strong_auth')
                            ->label('Passkey of tweestapsverificatie verplicht')
                            ->helperText('Leden moeten een passkey of authenticator-app instellen. De clubbeheerder kan dit ook zelf aanzetten.')
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Actief')
                            ->helperText('Leden van een inactieve club kunnen niet inloggen, de publieke pagina’s zijn offline en de Nevobo-sync slaat de club over.')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
                Section::make('Abonnement')
                    ->description('Het pakket bepaalt welke modules de club heeft en hoeveel teams, sporthallen en beheerders erin passen. Modules buiten het pakket ziet de club vergrendeld, met een upgrade-knop.')
                    ->icon('heroicon-o-credit-card')
                    ->aside()
                    ->schema([
                        Select::make('plan_id')
                            ->label('Pakket')
                            ->relationship('plan', 'name', fn (Builder $query) => $query->orderBy('sort_order')->orderBy('id'))
                            ->getOptionLabelFromRecordUsing(fn (Plan $record): string => $record->name.($record->formattedPrice() ? ' · '.$record->formattedPrice() : ''))
                            ->required()
                            ->live(),
                        Html::make(fn (Get $get): HtmlString => self::planSummary(Plan::query()->find($get('plan_id'))))
                            ->visible(fn (Get $get): bool => filled($get('plan_id'))),
                        Grid::make(3)->schema([
                            DatePicker::make('trial_ends_at')
                                ->label('Proefperiode tot')
                                ->helperText('Leeg = geen proefperiode.'),
                            DatePicker::make('subscription_starts_at')
                                ->label('Abonnement vanaf'),
                            DatePicker::make('subscription_ends_at')
                                ->label('Abonnement tot')
                                ->afterOrEqual('subscription_starts_at')
                                ->helperText('Leeg = loopt door.'),
                        ]),
                    ]),
            ]);
    }

    /** What the chosen plan gives the club: limits and the modules, locked ones struck through. */
    private static function planSummary(?Plan $plan): HtmlString
    {
        if ($plan === null) {
            return new HtmlString('');
        }

        $limits = collect(PlanLimit::cases())
            ->map(fn (PlanLimit $limit): string => '<span><strong>'.e($plan->limit($limit) ?? 'Onbeperkt').'</strong> '.e(Str::lower($limit->getLabel())).'</span>')
            ->implode('');

        $modules = collect(PlannerFeature::cases())
            ->map(fn (PlannerFeature $feature): string => $plan->includes($feature)
                ? '<li class="is-included">'.e($feature->getLabel()).'</li>'
                : '<li class="is-locked">'.e($feature->getLabel()).'</li>')
            ->implode('');

        return new HtmlString('<div class="vp-plan-summary"><div class="vp-plan-summary-limits">'.$limits.'</div><ul>'.$modules.'</ul></div>');
    }
}
