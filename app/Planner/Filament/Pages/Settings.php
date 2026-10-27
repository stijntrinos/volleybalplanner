<?php

namespace App\Planner\Filament\Pages;

use App\Planner\Filament\Concerns\HasPlannerPage;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Pages\Concerns\BuildsSettingsTabs;
use App\Planner\Models\ActivityColor;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\UserPreference;
use App\Planner\Services\Duties\DutyRules;
use App\Support\Tenancy\CurrentClub;
use App\Support\Tenancy\ReadOnlyClub;
use BackedEnum;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Personal planner preferences plus the club-wide planner settings, including
 * the club's branding and how its own teams are recognised.
 *
 * @property-read Schema $form
 */
class Settings extends Page implements HasForms
{
    use BuildsSettingsTabs, HasPlannerPage, InteractsWithForms;

    /** Club-wide columns saved from the form, when present in the state. */
    private const SETTING_FIELDS = [
        'main_venue_id',
        'season_start', 'season_end', 'competition_start', 'competition_end',
        'youth_spring_start', 'youth_spring_end', 'internal_senior_deadline',
        'training_period_start', 'training_period_end',
        'buffer_minutes', 'cache_ttl_minutes', 'admin_override_external_error',
        'points_referee_duty', 'points_scorer_duty', 'points_hall_duty', 'points_volleystars',
        'max_duties_per_week', 'max_duties_per_day', 'max_hall_duties_per_day', 'home_player_priority',
        'min_days_between_duties', 'display_match_duration', 'half_1_start', 'half_1_end', 'half_2_start', 'half_2_end',
        'schedule_footer', 'hall_duty_weekdays', 'hall_duty_exceptions', 'team_group_order',
        'request_reasons', 'duty_request_reasons', 'requests_open',
        'team_name_patterns', 'logo_path', 'primary_color', 'schedule_tagline',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::MASTER_DATA;

    protected static ?int $navigationSort = 26;

    protected string $view = 'planner::filament.pages.settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Plannerinstellingen');
    }

    public function getTitle(): string
    {
        return __('Plannerinstellingen');
    }

    public function mount(): void
    {
        $club = app(CurrentClub::class)->require();
        $settings = PlannerSetting::current();
        $preference = UserPreference::query()->firstOrCreate(['user_id' => auth()->id()]);

        $this->form->fill([
            'hall_duty_weekdays' => $settings->hall_duty_weekdays ?? PlannerSetting::HALL_DUTY_WEEKDAYS_DEFAULT,
            'hall_duty_exceptions' => $settings->hallDutyExceptions(),
            'team_group_order' => $settings->teamGroupOrder(),
            'team_name_patterns' => array_values($settings->team_name_patterns ?? []),
            'duty_rules' => $this->dutyRulesToForm($settings->duty_rules),
            'request_link' => route('planner.public.match-change-request', ['club' => $club]),
            'duty_request_link' => route('planner.public.duty-change-request', ['club' => $club]),
            'activity_colors' => ActivityColor::query()
                ->orderBy('type')
                ->get(['type', 'color'])
                ->map(static fn (ActivityColor $color): array => ['type' => $color->type, 'color' => $color->color])
                ->all(),
        ] + $settings->only(self::SETTING_FIELDS) + [
            'default_venue_id' => $preference->default_venue_id,
            'overlap_policy' => $preference->overlap_policy->value,
            'publish_with_conflicts' => (bool) $preference->publish_with_conflicts,
            'show_trainings' => (bool) ($preference->show_trainings ?? true),
        ]);
    }

    public function getSubheading(): ?string
    {
        return __('Beheer je persoonlijke plannerweergave en de gezamenlijke regels voor planning, taken en exports.');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make(__('Plannerinstellingen'))
                ->persistTabInQueryString('section')
                ->tabs([
                    $this->preferencesTab(),
                    $this->brandingTab(),
                    $this->planningTab(),
                    $this->dutiesTab(),
                    $this->requestsTab(),
                    $this->websiteTab(),
                    $this->colorsTab(),
                    $this->technicalTab(),
                ])
                ->columnSpanFull(),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $preference = [
            'default_venue_id' => $data['default_venue_id'] ?? null,
            'overlap_policy' => $data['overlap_policy'],
            'publish_with_conflicts' => $data['publish_with_conflicts'] ?? false,
        ];
        if (array_key_exists('show_trainings', $data)) {
            $preference['show_trainings'] = $data['show_trainings'];
        }
        UserPreference::query()->updateOrCreate(['user_id' => auth()->id()], $preference);
        $this->saveColors($data);

        // Anyone with page access may change their own preferences; the
        // settings below apply to the whole club and need an explicit right.
        if (!auth()->user()?->can('Update:PlannerSetting')) {
            Notification::make()->title(__('Je voorkeuren zijn opgeslagen'))
                ->body(ReadOnlyClub::active()
                    ? __('De clubbrede plannerinstellingen zijn ongewijzigd gebleven: de vereniging staat in de leesstand.')
                    : __('De clubbrede plannerinstellingen zijn ongewijzigd gebleven; daarvoor mis je het recht.'))
                ->warning()->send();

            return;
        }

        $values = collect($data)->only(self::SETTING_FIELDS)->all();
        if (array_key_exists('team_name_patterns', $values)) {
            $values['team_name_patterns'] = $this->list($values['team_name_patterns'] ?? []);
        }
        if (array_key_exists('duty_rules', $data)) {
            $values['duty_rules'] = $this->dutyRulesFromForm($data['duty_rules'] ?? []);
        }

        PlannerSetting::current()->update($values);
        Notification::make()->title(__('Plannerinstellingen opgeslagen'))->success()->send();
    }

    /**
     * Activity colours keep their own permission: who may edit them without
     * being allowed to change the club-wide settings can still do so here.
     *
     * Without the key the tab was not in the form and nothing was filled in to
     * sync; without that check the cleanup below would delete every colour.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveColors(array $data): void
    {
        if (!array_key_exists('activity_colors', $data) || !auth()->user()?->can('Update:ActivityColor')) {
            return;
        }

        $kept = [];
        foreach ((array) $data['activity_colors'] as $row) {
            $type = trim((string) ($row['type'] ?? ''));
            $color = trim((string) ($row['color'] ?? ''));
            if ($type === '' || $color === '') {
                continue;
            }

            ActivityColor::query()->updateOrCreate(['type' => $type], ['color' => $color]);
            $kept[] = $type;
        }

        // The club scope limits this delete to the current club.
        ActivityColor::query()->whereNotIn('type', $kept)->delete();
    }

    /** @return array<string, mixed> */
    private function dutyRulesToForm(?array $rules): array
    {
        $values = (new DutyRules($rules))->toArray();

        return [
            'restricted_referee_teams' => array_values((array) $values['restricted_referee_teams']),
            'scorer_heavy_teams' => array_values((array) $values['scorer_heavy_teams']),
            'scorer_cutoff' => substr((string) $values['scorer_cutoff'], 0, 5),
            'home_city' => (string) $values['home_city'],
            'lower_class_terms' => array_values((array) $values['lower_class_terms']),
        ];
    }

    /**
     * Who referees, scores or does hall duty lives on the team; only the
     * club-wide rules are stored here.
     *
     * @return array<string, mixed>
     */
    private function dutyRulesFromForm(array $data): array
    {
        return [
            'restricted_referee_teams' => $this->list($data['restricted_referee_teams'] ?? []),
            'scorer_heavy_teams' => $this->list($data['scorer_heavy_teams'] ?? []),
            'scorer_cutoff' => substr((string) ($data['scorer_cutoff'] ?? ''), 0, 5) ?: DutyRules::DEFAULTS['scorer_cutoff'],
            'home_city' => trim((string) ($data['home_city'] ?? '')),
            'lower_class_terms' => $this->list($data['lower_class_terms'] ?? []),
        ];
    }

    /** @return list<string> */
    private function list(mixed $values): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            (array) $values,
        ), static fn (string $value): bool => $value !== ''));
    }
}
