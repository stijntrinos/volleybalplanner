<?php

namespace Tests\Unit\Planner;

use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Support\Weekday;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The weekday used to be computed in four places, two of them via the Dutch
 * Carbon locale. Without that locale on the server the result was "saturday"
 * and the planner found no training at all, without any error.
 */
class WeekdayTest extends TestCase
{
    public function test_the_three_notations_belong_to_the_same_day(): void
    {
        $saturday = '2026-09-12';

        $this->assertSame('zaterdag', Weekday::name($saturday));
        $this->assertSame('Za', Weekday::abbreviation($saturday));
        $this->assertSame(6, Weekday::number($saturday));
    }

    public function test_sunday_counts_as_zero_like_in_the_availability_rules(): void
    {
        $sunday = CarbonImmutable::parse('2026-09-13');

        $this->assertSame('zondag', Weekday::name($sunday));
        $this->assertSame(0, Weekday::number($sunday));
        $this->assertSame($sunday->dayOfWeek, Weekday::number($sunday));
    }

    public function test_the_result_does_not_depend_on_the_carbon_locale(): void
    {
        $original = CarbonImmutable::getLocale();
        CarbonImmutable::setLocale('en');

        try {
            $this->assertSame('woensdag', Weekday::name('2026-09-16'));
        } finally {
            CarbonImmutable::setLocale($original);
        }
    }

    public function test_the_models_share_the_same_list(): void
    {
        $this->assertSame(Weekday::NAMES, Training::WEEKDAYS);
        $this->assertSame(Weekday::ABBREVIATIONS, Team::WEEKDAYS);
        $this->assertSame('maandag', Weekday::nameFromAbbreviation('Ma'));
        $this->assertSame('Zo', Weekday::abbreviationFromName('zondag'));
        $this->assertNull(Weekday::nameFromAbbreviation('Xx'));
    }

    public function test_all_seven_days_convert_back_and_forth(): void
    {
        foreach (Weekday::NAMES as $index => $name) {
            $date = CarbonImmutable::parse('2026-09-14')->addDays($index);

            $this->assertSame($name, Weekday::name($date));
            $this->assertSame($name, Weekday::nameFromAbbreviation(Weekday::abbreviation($date)));
            $this->assertSame($date->dayOfWeek, Weekday::number($date));
        }
    }
}
