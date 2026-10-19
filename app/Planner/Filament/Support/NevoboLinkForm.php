<?php

namespace App\Planner\Filament\Support;

use App\Models\Club;
use App\Planner\Exceptions\NevoboUnavailable;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Team;
use App\Planner\Services\Nevobo\NevoboClubDirectory;
use App\Planner\Services\Nevobo\NevoboClubLinker;
use App\Planner\Support\TeamCode;
use App\Support\Tenancy\CurrentClub;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

/**
 * The "link to Nevobo" flow, shared by the club page and the platform's club
 * action: search a club, tick its teams, link. The fetched team list travels
 * in a hidden field only to render the checklist; linking fetches it again
 * server-side, so a tampered form cannot inject teams or URLs.
 *
 * Preselection: on a first link every regular competition team is ticked;
 * when the club was linked to this Nevobo club before, only the teams it
 * already has are ticked and new Nevobo teams are marked "nieuw", so a team
 * the club removed on purpose does not silently come back.
 */
final class NevoboLinkForm
{
    /**
     * @param  Closure(): Club  $club
     * @return list<Component>
     */
    public static function components(Closure $club): array
    {
        return [
            Select::make('club_code')
                ->label(__('Vereniging bij de Nevobo'))
                ->placeholder(__('Zoek op naam of plaats'))
                ->searchable()
                ->searchPrompt(__('Typ minstens twee letters van de naam of plaats'))
                ->noSearchResultsMessage(__('Geen vereniging gevonden'))
                ->getSearchResultsUsing(static fn (?string $search): array => self::searchOptions((string) $search))
                ->getOptionLabelUsing(static fn (?string $value): string => self::clubLabel((string) $value))
                ->live()
                ->afterStateUpdated(static fn (?string $state, Set $set) => self::loadTeams($set, $club(), $state))
                ->required(),
            Hidden::make('nevobo_teams')->default([]),
            CheckboxList::make('team_ids')
                ->label(__('Teams'))
                ->helperText(__('Aangevinkte teams worden aangemaakt (als ze nog niet bestaan) en krijgen de Nevobo-feeds voor programma, uitslagen en stand.'))
                ->options(static fn (Get $get): array => collect((array) $get('nevobo_teams'))
                    ->mapWithKeys(static fn (array $team): array => [$team['nevobo_id'] => "{$team['code']} · {$team['name']}"])
                    ->all())
                ->descriptions(static fn (Get $get): array => collect((array) $get('nevobo_teams'))
                    ->mapWithKeys(static fn (array $team): array => [$team['nevobo_id'] => self::teamDescription($team)])
                    ->all())
                ->visible(static fn (Get $get): bool => (array) $get('nevobo_teams') !== [])
                ->bulkToggleable()
                ->searchable()
                ->columns(2)
                ->hintAction(
                    Action::make('refreshNevoboTeams')
                        ->label(__('Teams opnieuw ophalen'))
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->action(static fn (Get $get, Set $set) => self::loadTeams($set, $club(), $get('club_code'), fresh: true)),
                ),
        ];
    }

    /**
     * The form state to open with: the stored Nevobo club and its teams.
     *
     * @return array<string, mixed>
     */
    public static function initialState(Club $club): array
    {
        $code = self::storedCode($club);

        if ($code === null) {
            return ['club_code' => null, 'nevobo_teams' => [], 'team_ids' => []];
        }

        $state = ['club_code' => $code];
        self::loadTeams(static function (string $key, mixed $value) use (&$state): void {
            $state[$key] = $value;
        }, $club, $code);

        return $state + ['nevobo_teams' => [], 'team_ids' => []];
    }

    /**
     * Links the chosen teams and reports the outcome as a notification.
     *
     * @param  array<string, mixed>  $data
     * @return array{created: int, updated: int, sources: int}|null null when Nevobo was unavailable
     */
    public static function submit(Club $club, array $data): ?array
    {
        $ids = array_values(array_filter(array_map('strval', (array) ($data['team_ids'] ?? []))));

        if ($ids === []) {
            Notification::make()->title(__('Kies minstens één team om te koppelen'))->warning()->send();

            return null;
        }

        try {
            $result = app(NevoboClubLinker::class)->link($club, (string) ($data['club_code'] ?? ''), $ids);
        } catch (NevoboUnavailable $exception) {
            self::notifyFailure($exception);

            return null;
        }

        Notification::make()
            ->title(__('Gekoppeld aan de Nevobo'))
            ->body(__(':created teams aangemaakt, :updated bestaande teams bijgewerkt, :sources feeds toegevoegd of vernieuwd.', $result))
            ->success()
            ->send();

        return $result;
    }

    public static function storedCode(Club $club): ?string
    {
        return app(CurrentClub::class)->run($club, static fn (): ?string => PlannerSetting::query()->value('nevobo_club_code'));
    }

    /**
     * Fills the checklist for this Nevobo club; on a failure it is emptied
     * and the user gets a readable reason.
     *
     * @param  Set|callable(string, mixed): mixed  $set
     */
    public static function loadTeams(callable $set, Club $club, ?string $code, bool $fresh = false): void
    {
        $set('nevobo_teams', []);
        $set('team_ids', []);

        if (blank($code)) {
            return;
        }

        try {
            $teams = app(NevoboClubDirectory::class)->teams((string) $code, $fresh);
        } catch (NevoboUnavailable $exception) {
            self::notifyFailure($exception);

            return;
        }

        [$ownCodes, $relink] = app(CurrentClub::class)->run($club, static fn (): array => [
            Team::query()->pluck('code')->map(static fn (string $teamCode): string => TeamCode::key($teamCode))->flip()->all(),
            PlannerSetting::query()->value('nevobo_club_code') === strtoupper((string) $code),
        ]);

        $rows = array_map(static fn (array $team): array => [
            'nevobo_id' => $team['nevobo_id'],
            'code' => $team['code'],
            'name' => $team['name'],
            'standing_text' => $team['standing_text'],
            'regular' => $team['regular'],
            'exists' => isset($ownCodes[TeamCode::key($team['code'])]),
        ], $teams);

        $set('nevobo_teams', $rows);
        $set('team_ids', array_values(array_map(
            static fn (array $row): string => $row['nevobo_id'],
            array_filter($rows, static fn (array $row): bool => $row['exists'] || (!$relink && $row['regular'])),
        )));

        if ($teams === []) {
            Notification::make()->title(__('Deze vereniging heeft dit seizoen geen teams bij de Nevobo'))->warning()->send();
        }
    }

    /** @return array<string, string> */
    private static function searchOptions(string $search): array
    {
        try {
            $clubs = app(NevoboClubDirectory::class)->search($search);
        } catch (NevoboUnavailable $exception) {
            self::notifyFailure($exception);

            return [];
        }

        return collect($clubs)
            ->mapWithKeys(static fn (array $club): array => [$club['code'] => self::formatClub($club)])
            ->all();
    }

    private static function clubLabel(string $code): string
    {
        try {
            $club = app(NevoboClubDirectory::class)->club($code);
        } catch (NevoboUnavailable) {
            $club = null;
        }

        return $club === null ? $code : self::formatClub($club);
    }

    /** @param  array{code: string, name: string, official_name: string, city: string, region: string}  $club */
    private static function formatClub(array $club): string
    {
        $details = array_filter([$club['city'], $club['region'] !== '' ? __('regio :region', ['region' => $club['region']]) : null]);

        return $club['name'].($club['official_name'] !== $club['name'] ? " ({$club['official_name']})" : '')
            .($details !== [] ? ' · '.implode(', ', $details) : '')
            ." · {$club['code']}";
    }

    /** @param  array<string, mixed>  $team */
    private static function teamDescription(array $team): string
    {
        return implode(' · ', array_filter([
            $team['standing_text'] ?: __('Geen competitiestand'),
            $team['exists'] ? __('bestaat al') : __('nieuw'),
            $team['regular'] ? null : __('geen reguliere competitie'),
        ]));
    }

    private static function notifyFailure(NevoboUnavailable $exception): void
    {
        Notification::make()
            ->title(__('Nevobo niet bereikbaar'))
            ->body($exception->getMessage())
            ->danger()
            ->send();
    }
}
