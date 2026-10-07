<?php

namespace Tests\Unit\Planner;

use App\Planner\Support\ClubTeamMatcher;
use App\Planner\Support\TeamCode;
use PHPUnit\Framework\TestCase;

class ClubTeamMatcherTest extends TestCase
{
    private ClubTeamMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = new ClubTeamMatcher(['AET', ' AETOS ', '', 'AETOS']);
    }

    public function test_patterns_are_cleaned_and_longest_first(): void
    {
        $this->assertSame(['AETOS', 'AET'], $this->matcher->patterns());
        $this->assertTrue($this->matcher->hasPatterns());
    }

    public function test_it_recognises_own_teams_as_whole_words(): void
    {
        $this->assertTrue($this->matcher->isOwnTeam('AETOS Plauti DS 1'));
        $this->assertTrue($this->matcher->isOwnTeam('aet hs 2'));
        $this->assertTrue($this->matcher->isOwnTeam('Rebelle HS 2 - AETOS HS 3'));
        $this->assertFalse($this->matcher->isOwnTeam('Aetosia HS 1'));
        $this->assertFalse($this->matcher->isOwnTeam('Orion DS 3'));
        $this->assertFalse($this->matcher->isOwnTeam(null));
    }

    public function test_starts_with_club_name_decides_home_or_away(): void
    {
        $this->assertTrue($this->matcher->startsWithClubName('AETOS HS 1'));
        $this->assertFalse($this->matcher->startsWithClubName('Orion HS 2 - AETOS HS 1'));
    }

    public function test_strip_club_prefix(): void
    {
        $this->assertSame('HS 1', $this->matcher->stripClubPrefix('AETOS HS 1'));
        $this->assertSame('Plauti DS 1', $this->matcher->stripClubPrefix(' aet Plauti DS 1'));
        $this->assertSame('Orion HS 1', $this->matcher->stripClubPrefix('Orion HS 1'));
    }

    public function test_only_the_part_after_the_club_name_counts_for_a_team_code(): void
    {
        $this->assertTrue($this->matcher->isOwnTeamWithCode('AETOS Plauti DS 1', 'DS 1'));
        $this->assertFalse($this->matcher->isOwnTeamWithCode('AETOS DS 10', 'DS 1'));
        $this->assertFalse($this->matcher->isOwnTeamWithCode('Rebelle HS 2 - AETOS HS 3', 'HS 2'));
        $this->assertFalse($this->matcher->isOwnTeamWithCode('Orion HS 2', 'HS 2'));
        $this->assertSame(' HS 3', $this->matcher->textAfterClubName('Rebelle HS 2 - AETOS HS 3'));
        $this->assertNull($this->matcher->textAfterClubName('Orion HS 2'));
    }

    public function test_own_team_code_and_starts_with_own_team(): void
    {
        $this->assertSame('HS3', $this->matcher->ownTeamCode('Rebelle HS 2 - AETOS HS 3'));
        $this->assertSame('DS1', $this->matcher->ownTeamCode('AETOS Plauti ds1'));
        $this->assertNull($this->matcher->ownTeamCode('AETOS Recreanten'));
        $this->assertTrue($this->matcher->startsWithOwnTeam('AETOS XZ 1', 'XZ 1'));
        $this->assertTrue($this->matcher->startsWithOwnTeam('AETOS XZ1 ', 'XZ 1'));
        $this->assertFalse($this->matcher->startsWithOwnTeam('Orion - AETOS XZ 1', 'XZ 1'));
        $this->assertFalse($this->matcher->startsWithOwnTeam('AETOS XZ 10', 'XZ 1'));
    }

    public function test_without_patterns_nothing_is_recognised(): void
    {
        $matcher = new ClubTeamMatcher([]);

        $this->assertFalse($matcher->hasPatterns());
        $this->assertFalse($matcher->isOwnTeam('AETOS HS 1'));
        $this->assertFalse($matcher->startsWithClubName('AETOS HS 1'));
        $this->assertSame('AETOS HS 1', $matcher->stripClubPrefix('AETOS HS 1'));
        $this->assertNull($matcher->ownTeamCode('AETOS HS 1'));
    }

    public function test_team_code_helpers(): void
    {
        $this->assertTrue(TeamCode::equals('HS 1', 'hs-1'));
        $this->assertSame('women', TeamCode::category('DS 4'));
        $this->assertSame(TeamCode::CATEGORY_OTHER, TeamCode::category('MA+'));
        $this->assertLessThan(TeamCode::sortKey('DS 10'), TeamCode::sortKey('DS 2'));
        $this->assertSame(0, preg_match((string) TeamCode::pattern('MA+'), 'AETOS MA 4'));
    }
}
