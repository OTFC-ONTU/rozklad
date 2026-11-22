<?php

namespace Tests\Unit;

use App\Enums\WeekParity;
use PHPUnit\Framework\TestCase;

class WeekParityTest extends TestCase
{
    public function test_overlaps(): void
    {
        $this->assertTrue(WeekParity::Every->overlaps(WeekParity::Numerator));
        $this->assertTrue(WeekParity::Denominator->overlaps(WeekParity::Every));
        $this->assertTrue(WeekParity::Numerator->overlaps(WeekParity::Numerator));
        $this->assertFalse(WeekParity::Numerator->overlaps(WeekParity::Denominator));
    }

    public function test_overlapping_lists_compatible_values(): void
    {
        $this->assertSame([WeekParity::Every, WeekParity::Numerator], WeekParity::Numerator->overlapping());
        $this->assertSame(WeekParity::cases(), WeekParity::Every->overlapping());
    }
}
