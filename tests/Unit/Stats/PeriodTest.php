<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit\Stats;

use AdrienGras\Umami\Stats\Period;
use PHPUnit\Framework\TestCase;

final class PeriodTest extends TestCase
{
    public function testBetweenBuildsEpochQuery(): void
    {
        $query = Period::between(1000, 2000, timezone: 'UTC')->toQuery();

        $this->assertEquals([
            'startAt' => 1000,
            'endAt' => 2000,
            'timezone' => 'UTC',
        ], $query);
    }

    public function testBetweenDatesBuildsDateQuery(): void
    {
        $query = Period::betweenDates('2026-01-01', '2026-01-31')->toQuery();

        $this->assertEquals([
            'startDate' => '2026-01-01',
            'endDate' => '2026-01-31',
        ], $query);
    }

    public function testUnitAndCompareAreCarried(): void
    {
        $query = Period::between(1, 2, unit: 'day', compare: 'prev')->toQuery();

        $this->assertEquals([
            'startAt' => 1,
            'endAt' => 2,
            'unit' => 'day',
            'compare' => 'prev',
        ], $query);
    }
}
