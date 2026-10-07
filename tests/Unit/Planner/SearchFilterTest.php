<?php

namespace Tests\Unit\Planner;

use App\Planner\Support\SearchFilter;
use PHPUnit\Framework\TestCase;

class SearchFilterTest extends TestCase
{
    public function test_all_words_must_occur_ignoring_accents_and_spaces(): void
    {
        $text = 'Club DS 1 - Orion DS 2 Sporthal Arnhem';

        $this->assertTrue(SearchFilter::contains($text, 'ds 1 arnhem'));
        $this->assertTrue(SearchFilter::contains($text, 'ds1'));
        $this->assertTrue(SearchFilter::contains($text, ''));
        $this->assertFalse(SearchFilter::contains($text, 'ds 1 utrecht'));
        $this->assertTrue(SearchFilter::contains('Café Overzicht', 'cafe'));
    }

    public function test_team_kind_words_find_the_team_code_at_a_word_start(): void
    {
        $this->assertTrue(SearchFilter::contains('Club DS 1 - Orion DS 2', 'dames'));
        $this->assertTrue(SearchFilter::contains('Club HS 3 - Orion HS 1', 'heren'));
        $this->assertFalse(SearchFilter::contains('Club Hands 1', 'dames'));
    }

    public function test_same_team_compares_on_the_end(): void
    {
        $this->assertTrue(SearchFilter::isSameTeam('Club Sponsor DS 1', 'DS 1'));
        $this->assertFalse(SearchFilter::isSameTeam('Club Sponsor DS 10', 'DS 1'));
        $this->assertFalse(SearchFilter::isSameTeam(null, 'DS 1'));
        $this->assertFalse(SearchFilter::isSameTeam('Club DS 1', ''));
    }
}
