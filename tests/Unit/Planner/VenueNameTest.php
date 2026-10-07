<?php

namespace Tests\Unit\Planner;

use App\Planner\Support\VenueName;
use PHPUnit\Framework\TestCase;

class VenueNameTest extends TestCase
{
    public function test_spelling_variants_and_addresses_compare_equal(): void
    {
        $this->assertTrue(VenueName::equals('De Laar', 'de laar'));
        $this->assertTrue(VenueName::equals('Midachtensingel', 'Middachtensingel'));
        $this->assertTrue(VenueName::equals('Sporthal Middachtensingel, Middachtensingel 4, 6825HN Arnhem', 'Sporthal Middachtensingel'));
        $this->assertTrue(VenueName::equals('Sportcentrum Café', 'sportcentrum cafe'));
        $this->assertFalse(VenueName::equals('De Laar', 'Rijkerswoerd'));
        $this->assertSame('', VenueName::key(null));
    }
}
