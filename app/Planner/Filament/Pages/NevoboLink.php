<?php

namespace App\Planner\Filament\Pages;

use App\Planner\Filament\Concerns\HasPlannerPage;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Support\NevoboLinkForm;
use App\Support\Tenancy\CurrentClub;
use BackedEnum;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Sets up the club's teams and Nevobo feeds from the federation's team list
 * instead of entering every feed URL by hand. See NevoboLinkForm.
 *
 * @property-read Schema $form
 */
class NevoboLink extends Page implements HasForms
{
    use HasPlannerPage, InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::MASTER_DATA;

    protected static ?int $navigationSort = 25;

    protected string $view = 'planner::filament.pages.nevobo-link';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Nevobo-koppeling');
    }

    public function getTitle(): string
    {
        return __('Nevobo-koppeling');
    }

    public function getSubheading(): ?string
    {
        return __('Zoek je vereniging bij de Nevobo, kies de teams en de planner zet teams en feeds voor je klaar.');
    }

    public function mount(): void
    {
        $this->form->fill(NevoboLinkForm::initialState(app(CurrentClub::class)->require()));
    }

    public function form(Schema $schema): Schema
    {
        $club = static fn () => app(CurrentClub::class)->require();

        return $schema->components([
            Section::make(__('Vereniging en teams'))
                ->description(fn (): string => ($code = NevoboLinkForm::storedCode($club()))
                    ? __('Gekoppeld aan Nevobo-vereniging :code. Opnieuw koppelen werkt de feeds bij; teams die nieuw zijn bij de Nevobo staan uit tot je ze aanvinkt.', ['code' => $code])
                    : __('Nog niet gekoppeld. Reguliere competitieteams staan standaard aan; CMV en Volley Stars kun je zelf aanvinken.'))
                ->icon(Heroicon::OutlinedLink)
                ->schema(NevoboLinkForm::components($club)),
        ])->statePath('data');
    }

    public function link(): void
    {
        if (!auth()->user()?->can('Create:Team')) {
            Notification::make()->title(__('Je mist het recht om teams aan te maken'))->danger()->send();

            return;
        }

        $data = $this->form->getState();
        $club = app(CurrentClub::class)->require();

        if (NevoboLinkForm::submit($club, $data) !== null) {
            $this->form->fill(NevoboLinkForm::initialState($club));
        }
    }
}
