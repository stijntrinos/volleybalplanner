<?php

namespace Tests\Unit\Planner;

use App\Planner\Support\MatchCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MatchCodeTest extends TestCase
{
    /**
     * The three forms come from real feeds of pools 3CD, JBHA1 and BD1A03. The
     * pool code is padded to six characters, so the shorter the pool, the more
     * spaces take its place.
     *
     * @return array<string, array{string, string}>
     */
    public static function feedCodes(): array
    {
        return [
            'short pool, three spaces' => ['50003CD   CH', '3CD-CH'],
            'pool of five, one space' => ['5000JBHA1 BA', 'JBHA1-BA'],
            'pool of six, no space' => ['5000BD1A03BD', 'BD1A03-BD'],
            'lower case from the feed' => ['5000bd1a03bd', 'BD1A03-BD'],
            'second fixture' => ['5000JBHA1 BA-1', 'JBHA1-BA-2'],
            'second fixture, short pool' => ['5000D4L   CH-1', 'D4L-CH-2'],
            'third fixture' => ['5000JBHA1 BA-2', 'JBHA1-BA-3'],
        ];
    }

    #[DataProvider('feedCodes')]
    public function test_the_feed_format_becomes_the_federation_notation(string $feed, string $expected): void
    {
        $this->assertSame($expected, MatchCode::normalize($feed));
    }

    public function test_a_code_that_is_already_correct_does_not_change(): void
    {
        $this->assertSame('BD1E16-BC', MatchCode::normalize('BD1E16-BC'));
        $this->assertSame('3CD-CH', MatchCode::normalize('3CD-CH'));
    }

    public function test_synthetic_codes_are_left_alone(): void
    {
        $this->assertSame('RSS-CLUBHS1-GEMINIHS2', MatchCode::normalize('RSS-CLUBHS1-GEMINIHS2'));
    }

    public function test_empty_input_yields_nothing(): void
    {
        $this->assertNull(MatchCode::normalize(null));
        $this->assertNull(MatchCode::normalize('   '));
    }

    /**
     * Twelve characters starting with four digits is the signal; anything
     * shorter or longer is just a code and must not be pulled apart.
     */
    public function test_something_that_merely_looks_like_it_stays_whole(): void
    {
        $this->assertSame('5000BD1A03B', MatchCode::normalize('5000BD1A03B'));
        $this->assertSame('5000BD1A03BDE', MatchCode::normalize('5000BD1A03BDE'));
        $this->assertSame('500-BD1A03BD', MatchCode::normalize('500 BD1A03BD'));
    }

    public function test_a_first_fixture_keeps_no_number(): void
    {
        $this->assertSame('JBHA1-BA', MatchCode::normalize('5000JBHA1 BA'));
    }

    public function test_the_feed_number_counts_up_by_one(): void
    {
        $this->assertSame('JBHA1-BA-2', MatchCode::normalize('5000JBHA1 BA-1'));
        $this->assertSame('JBHA1-BA-3', MatchCode::normalize('5000JBHA1 BA-2'));
    }

    public function test_a_stored_code_also_gets_the_right_number(): void
    {
        $this->assertSame('JBHA1-BA-2', MatchCode::repairStored('5000JBHA1-BA-1'));
        $this->assertSame('3CD-AD', MatchCode::repairStored('50003CD-AD'));
    }

    public function test_the_key_links_feed_and_planning_file(): void
    {
        $this->assertSame(MatchCode::key('BD1E16-BC'), MatchCode::key('5000BD1E16BC'));
        $this->assertSame(MatchCode::key('3CD-CH'), MatchCode::key('50003CD   CH'));
        $this->assertNotSame(MatchCode::key('BD1E16-BC'), MatchCode::key('BD1E16-BD'));
    }
}
