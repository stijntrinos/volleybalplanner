<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Plan;
use App\Models\User;
use App\Planner\Enums\ChangeRequestKind;
use App\Planner\Enums\ChangeRequestStatus;
use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\DutyType;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\NevoboChangeStatus;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\ChangeRequest;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Support\ClubRole;
use App\Support\Tenancy\CurrentClub;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * A demo club with the data the design system was drawn with: two halls,
 * a published and a draft version, a full Saturday, open requests and a
 * Nevobo change. `php artisan db:seed --class=DemoSeeder`, then sign in as
 * demo@volleybalplanner.test / password.
 */
class DemoSeeder extends Seeder
{
    public const CLUB = 'VC Voorbeeld';

    public const EMAIL = 'demo@volleybalplanner.test';

    public function run(): void
    {
        // A known password and a platform admin: never on a live server.
        if (app()->isProduction()) {
            throw new RuntimeException('De DemoSeeder draait niet in productie.');
        }

        Artisan::call('planner:sync-roles');

        $club = Club::query()->firstOrCreate(
            ['slug' => 'vc-voorbeeld'],
            ['name' => self::CLUB],
        );
        // Vereniging: everything but zitvolleybal, so a locked module shows too.
        $club->update(['plan_id' => Plan::query()->where('slug', 'vereniging')->value('id')]);

        $user = User::query()->firstOrCreate(
            ['email' => self::EMAIL],
            ['name' => 'Stijn', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        // Also a platform admin, so /admin can be tried with the same account.
        $user->forceFill(['is_platform_admin' => true])->save();
        $user->clubs()->syncWithoutDetaching([$club->getKey()]);
        $user->assignClubRole($club, ClubRole::ClubAdmin);

        app(CurrentClub::class)->run($club, fn () => $this->seedClub());
    }

    private function seedClub(): void
    {
        if (ScheduleVersion::query()->exists()) {
            return;
        }

        PlannerSetting::current()->update([
            'team_name_patterns' => [self::CLUB],
            'primary_color' => '#7A2E3A',
            'schedule_tagline' => 'Check je kleedkamer bij de ingang · Veel speelplezier en een sportieve wedstrijd',
        ]);

        $haven = Venue::query()->create(['venue_code' => 'HAVEN', 'name' => 'De Haven', 'city' => 'Voorbeeldstad', 'is_managed' => true, 'has_duties' => true, 'for_matches' => true, 'for_trainings' => true, 'is_active' => true]);
        $noord = Venue::query()->create(['venue_code' => 'NOORD', 'name' => 'Sportcentrum Noord', 'city' => 'Voorbeeldstad', 'is_managed' => false, 'has_duties' => false, 'for_matches' => true, 'for_trainings' => false, 'is_active' => true]);
        PlannerSetting::current()->update(['main_venue_id' => $haven->id]);

        $courts = [];
        foreach ([1, 2, 3] as $number) {
            $courts["haven{$number}"] = Court::query()->create(['venue_id' => $haven->id, 'name' => (string) $number, 'sort_order' => $number, 'is_active' => true]);
        }
        $courts['noord1'] = Court::query()->create(['venue_id' => $noord->id, 'name' => '1', 'sort_order' => 1, 'is_active' => true]);

        $teams = [];
        foreach (['DS 1', 'DS 2', 'DS 3', 'DS 4', 'DS 6', 'DS 8', 'DS 9', 'DS 10', 'HS 1', 'HS 2', 'HS 3', 'HS 4', 'HS 5', 'HS 9', 'MA 2', 'MB 1', 'MC 1', 'MC 4', 'JA 1', 'JA 2', 'JB 1', 'JB 2', 'JB 3', 'JC 2'] as $code) {
            $teams[$code] = Team::query()->create([
                'code' => $code,
                'name' => self::CLUB.' '.$code,
                'default_weekday' => 'Za',
                'default_venue_id' => $haven->id,
                'include_in_duties' => true,
                'has_duties' => true,
                'provides_referee' => true,
                'nevobo_referee' => in_array($code, ['DS 1', 'HS 1', 'HS 3'], true),
                'plays_on_two_courts' => $code === 'DS 1',
                'scores_own_match' => false,
            ]);
        }

        Training::query()->create(['team_id' => $teams['HS 5']->id, 'venue_id' => $haven->id, 'weekday' => 'zaterdag', 'start_time' => '16:45', 'end_time' => '18:45', 'court' => 3]);
        Training::query()->create(['team_id' => $teams['DS 2']->id, 'venue_id' => $haven->id, 'weekday' => 'dinsdag', 'start_time' => '20:00', 'end_time' => '22:00', 'court' => 1]);

        $saturday = CarbonImmutable::now()->next('Saturday');
        $sunday = $saturday->addDay();

        $published = ScheduleVersion::query()->create(['name' => 'v39', 'status' => ScheduleVersionStatus::Published, 'published_at' => now()->subDays(2)]);
        $draft = ScheduleVersion::query()->create(['name' => 'v40', 'status' => ScheduleVersionStatus::Draft, 'note' => 'Concept na de Nevobo-wijzigingen van oktober']);

        $matches = [
            // [date, time, code, home, away, venue, court, referee, scorer, options]
            [$saturday, '09:00', 'XCHC1-CF', 'MC 1', 'Smash ’88 MC 1', $haven, 'haven1', 'DS 4', 'MC 1', []],
            [$saturday, '10:45', 'JBHA1-BD', 'JB 1', 'Zuiderzee JB 1', $haven, 'haven1', 'HS 3', 'JB 1', []],
            [$saturday, '12:30', 'JAHA1-DA', 'JA 1', 'Blok 3 JA 1', $haven, 'haven1', 'HS 5', 'MC 1', []],
            [$saturday, '14:30', '3CD-DE', 'DS 1', 'Netwerk DS 1', $haven, 'haven1', 'Nevobo', 'MB 1', ['second_court' => 'haven2', 'duration' => 150]],
            [$saturday, '17:00', 'HPE-IJ', 'HS 2', 'Zuiderzee HS 2', $haven, 'haven1', 'DS 1', 'HS 3', []],
            [$saturday, '09:00', 'XC2J1-AD', 'JC 2', 'Netwerk JC 1', $haven, 'haven2', 'DS 8', 'JC 2', []],
            [$saturday, '10:45', 'MBHC1-BA', 'MB 1', 'Smash ’88 MB 1', $haven, 'haven2', 'DS 1', 'MB 1', []],
            [$saturday, '12:30', 'D1L-DE', 'DS 4', 'Zuiderzee DS 2', $haven, 'haven2', 'HS 2', 'JC 2', []],
            [$saturday, '17:00', 'D1L-CF', 'HS 5', 'Smash ’88 HS 2', $haven, 'haven2', 'DS 3', 'DS 2', []],
            [$saturday, '09:00', 'JB2B1-AC', 'JB 3', 'Blok 3 JB 1', $haven, 'haven3', 'DS 4', 'JB 3', []],
            [$saturday, '10:45', 'MA1D1-BE', 'MA 2', 'Blok 3 MA 1', $haven, 'haven3', 'DS 1', 'MA 2', []],
            [$saturday, '12:30', 'D1M-IJ', 'DS 3', 'Netwerk DS 2', $haven, 'haven3', 'DS 2', 'JB 3', []],
            [$saturday, '14:30', 'HPE-BG', 'HS 3', 'Smash ’88 HS 1', $haven, 'haven3', 'Nevobo', 'JB 1', []],
            [$saturday, '09:00', 'XC4G1-AF', 'MC 4', 'Netwerk MC 1', $noord, 'noord1', 'JA 2', 'MC 4', []],
            [$saturday, '10:45', 'JB1B1-AE', 'JB 2', 'Zuiderzee JB 2', $noord, 'noord1', 'DS 8', 'JB 2', []],
            [$saturday, '12:30', 'JA1B1-DC', 'JA 2', 'Smash ’88 JA 1', $noord, 'noord1', 'HS 9', 'JA 2', []],
            [$sunday, '11:00', 'JB2B2-AE', 'JB 2', 'Netwerk JB 1', $noord, 'noord1', 'HS 9', 'JA 2', []],
            [$sunday, '13:00', 'D4L-HD', 'DS 6', 'Blok 3 DS 4', $noord, 'noord1', 'HS 4', null, []],
            [$saturday->addWeek(), '14:30', 'MA1D1-BD', 'MA 2', 'Smash ’88 MA 1', $haven, 'haven1', null, null, ['change' => ChangeStatus::ToChange]],
            [$saturday->addWeek(), '12:30', 'D1L-DF', 'DS 4', 'Blok 3 DS 2', $haven, 'haven2', 'MB 1', 'DS 4', []],
            [$saturday->addWeek(), '16:00', 'HPE-IK', 'HS 1', 'Zuiderzee HS 1', $haven, 'haven1', 'Nevobo', 'HS 2', ['second_court' => 'haven2', 'duration' => 150]],
            [$saturday->addWeeks(2), '20:45', 'H1D2-AC', 'HS 1', 'Zuiderzee HS 1', $noord, 'noord1', null, 'HS 2', ['change' => ChangeStatus::Requested]],
            [$saturday->addWeeks(3), '11:00', 'JA1B1-DD', 'JA 1', 'Blok 3 JA 1', $haven, 'haven1', 'DS 2', 'JA 1', []],
            [$saturday->addWeeks(9), '19:30', 'D4L-HE', 'DS 9', 'DS 10', $haven, 'haven1', 'HS 4', 'DS 10', ['change' => ChangeStatus::ToChange]],
            [$saturday->addWeeks(13), '11:00', 'JB2B1-AF', 'JB 2', 'Netwerk JB 1', $noord, 'noord1', 'DS 8', 'JB 2', ['nevobo' => true]],
        ];

        foreach ([$published, $draft] as $version) {
            foreach ($matches as [$date, $time, $code, $home, $away, $venue, $court, $referee, $scorer, $options]) {
                $isNevobo = $referee === 'Nevobo';
                $match = FixtureMatch::query()->create([
                    'version_id' => $version->id,
                    'date' => $date->toDateString(),
                    'time' => $time,
                    'duration_minutes' => $options['duration'] ?? 105,
                    'home_team_name' => self::CLUB.' '.$home,
                    'away_team_name' => $away,
                    'home_team_id' => $teams[$home]->id,
                    'match_code' => $code,
                    'match_type' => MatchType::Home,
                    'venue_id' => $venue->id,
                    'court_id' => $courts[$court]->id,
                    'second_court_id' => isset($options['second_court']) ? $courts[$options['second_court']]->id : null,
                    'referee' => $isNevobo ? null : $referee,
                    'nevobo_referee' => $isNevobo,
                    'scorer' => $scorer,
                    'change_status' => $options['change'] ?? null,
                ]);

                if ($options['nevobo'] ?? false) {
                    $match->update([
                        'nevobo_change_status' => NevoboChangeStatus::Conflict,
                        'nevobo_changed_at' => now()->subHours(3),
                        'nevobo_previous_date' => $date->toDateString(),
                        'nevobo_previous_time' => $time,
                        'nevobo_new_date' => $date->addDay()->toDateString(),
                        'nevobo_new_time' => '13:00',
                    ]);
                }
            }

            foreach ([
                ['08:15', '10:45', 'HS 3'], ['10:45', '12:30', 'HS 3'], ['12:30', '14:30', 'HS 5'], ['14:30', '17:00', 'DS 4'], ['17:00', '19:00', 'DS 2'],
            ] as [$start, $end, $team]) {
                HallDuty::query()->create(['version_id' => $version->id, 'date' => $saturday->toDateString(), 'start_time' => $start, 'end_time' => $end, 'team_id' => $teams[$team]->id, 'venue_id' => $haven->id, 'team_auto' => true]);
            }

            foreach ([
                [$saturday, '15:00', 'Zuiderzee DS 1', 'DS 1', 'D1L-AA'],
                [$saturday->addWeek(), '17:30', 'Smash ’88 HS 3', 'HS 2', 'HPE-BB'],
                [$sunday, '12:00', 'Netwerk MC 2', 'MC 4', 'XC4G1-CC'],
            ] as [$date, $time, $home, $away, $code]) {
                FixtureMatch::query()->create([
                    'version_id' => $version->id,
                    'date' => $date->toDateString(),
                    'time' => $time,
                    'duration_minutes' => 105,
                    'home_team_name' => $home,
                    'away_team_name' => self::CLUB.' '.$away,
                    'away_team_id' => $teams[$away]->id,
                    'match_code' => $code,
                    'match_type' => MatchType::Away,
                ]);
            }
        }

        $requested = FixtureMatch::query()->where('version_id', $published->id)->where('match_code', 'JA1B1-DD')->first();
        ChangeRequest::query()->create([
            'kind' => ChangeRequestKind::Match,
            'match_id' => $requested?->id,
            'match_code' => 'JA1B1-DD',
            'team_id' => $teams['JA 1']->id,
            'name' => 'Sanne de Vries',
            'email' => 'sanne@example.test',
            'reason' => 'Toernooi met de jeugdafdeling',
            'explanation' => 'Toernooi met de jeugdafdeling; voorkeur voor de zondag erna.',
            'proposed_date' => $saturday->addWeeks(3)->addDay()->toDateString(),
            'status' => ChangeRequestStatus::Open,
        ])->forceFill(['created_at' => now()->subDay()->setTime(21, 14)])->save();
        ChangeRequest::query()->create([
            'kind' => ChangeRequestKind::Duty,
            'duty_type' => DutyType::Scorer,
            'duty_date' => $saturday->addWeek()->toDateString(),
            'duty_time' => '12:30',
            'duty_location' => 'De Haven',
            'duty_description' => 'DS 4 – Blok 3 DS 2',
            'team_id' => $teams['DS 4']->id,
            'name' => 'Mark Jansen',
            'email' => 'mark@example.test',
            'reason' => 'Ruilen met een ander team',
            'explanation' => 'Ruilen met MB 1, in overleg met de trainer.',
            'status' => ChangeRequestStatus::Open,
        ])->forceFill(['created_at' => now()->setTime(8, 2)])->save();
    }
}
