<?php

namespace Tests\Unit\Planner;

use App\Planner\Services\Nevobo\NevoboTeamCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NevoboTeamCodeTest extends TestCase
{
    /** @return array<string, array{string, string, string}> */
    public static function teams(): array
    {
        return [
            'men' => ['/competitie/teams/cnh8q1u/heren/1', 'AETOS HS 1', 'HS 1'],
            'women with sponsor' => ['/competitie/teams/cnh8q1u/dames/10', 'AETOS Plauti DS 10', 'DS 10'],
            'girls A' => ['/competitie/teams/cnh8q1u/meiden-a/2', 'AETOS MA 2', 'MA 2'],
            'boys B' => ['/competitie/teams/cnh8q1u/jongens-b/3', 'AETOS JB 3', 'JB 3'],
            'girls C' => ['/competitie/teams/cnh8q1u/meiden-c/7', 'AETOS MC 7', 'MC 7'],
            'boys C' => ['/competitie/teams/cnh8q1u/jongens-c/1', 'AETOS JC 1', 'JC 1'],
            'sitting volleyball' => ['/competitie/teams/cnh8q1u/mix-zitvolleybal/1', 'AETOS XZ 1', 'XZ 1'],
            'cmv' => ['/competitie/teams/cnh8q1u/cmv-niveau-6/2', 'AETOS N6 2', 'N6 2'],
            'volley stars' => ['/competitie/teams/cnh8q1u/volley-stars-level-5/9', 'AETOS L5 9', 'L5 9'],
            'recreational' => ['/competitie/teams/ckm1h25/mix-recreatief/3', 'Volley2B XR 3', 'XR 3'],
            'no code in the name' => ['/competitie/teams/x1/beach-heren/2', 'Club Beach', 'BH 2'],
        ];
    }

    #[DataProvider('teams')]
    public function test_nevobo_team_ids_map_to_planner_codes(string $id, string $name, string $code): void
    {
        $this->assertSame($code, NevoboTeamCode::fromNevobo($id, $name));
    }

    public function test_an_unknown_id_has_no_code(): void
    {
        $this->assertNull(NevoboTeamCode::fromNevobo('/competitie/poules/regio-oost/x', 'AETOS HS 1'));
    }

    public function test_youth_formats_and_teams_without_standings_are_not_regular_competition(): void
    {
        $this->assertTrue(NevoboTeamCode::isRegularCompetition('/competitie/teams/a/dames/11', null));
        $this->assertTrue(NevoboTeamCode::isRegularCompetition('/competitie/teams/a/mix-zitvolleybal/1', '5e in Zitvolleybal Derde divisie'));
        $this->assertFalse(NevoboTeamCode::isRegularCompetition('/competitie/teams/a/cmv-niveau-6/1', '1e in CMV'));
        $this->assertFalse(NevoboTeamCode::isRegularCompetition('/competitie/teams/a/volley-stars-level-5/1', null));
        $this->assertFalse(NevoboTeamCode::isRegularCompetition('/competitie/teams/a/mix-recreatief/1', null));
    }
}
