<?php

namespace Tests\Unit\Planner;

use App\Planner\Support\TextMeasure;
use Tests\TestCase;

class TextMeasureTest extends TestCase
{
    public function test_a_short_line_keeps_the_full_size(): void
    {
        $this->assertSame(18, TextMeasure::fitting('Demo DS 4 - Gemini DS 3', 320, 18));
    }

    public function test_a_long_line_shrinks_until_it_fits(): void
    {
        $long = 'VV Demo JA 1 - Bielderman Koetsier/SSS JA 1';

        $size = TextMeasure::fitting($long, 280, 18);

        $this->assertLessThan(18, $size);
        $this->assertGreaterThan(9, $size);
    }

    public function test_more_space_gives_a_larger_font(): void
    {
        $long = 'VV Demo JA 1 - Bielderman Koetsier/SSS JA 1';

        $this->assertGreaterThan(
            TextMeasure::fitting($long, 200, 18),
            TextMeasure::fitting($long, 400, 18),
        );
    }

    public function test_the_result_stays_above_the_minimum(): void
    {
        // A card can become so narrow that nothing fits; then rather a
        // readable minimum than a font size of zero.
        $this->assertSame(12, TextMeasure::fitting('VV Demo JB 1 - SV Dynamo Apeldoorn JB 1', 20, 18, 12));
    }

    public function test_empty_text_or_no_space_changes_nothing(): void
    {
        $this->assertSame(18, TextMeasure::fitting('', 280, 18));
        $this->assertSame(18, TextMeasure::fitting('VV Demo DS 1 - Orion DS 2', 0, 18));
    }
}
