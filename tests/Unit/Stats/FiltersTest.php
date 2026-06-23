<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit\Stats;

use AdrienGras\Umami\Stats\Filters;
use PHPUnit\Framework\TestCase;

final class FiltersTest extends TestCase
{
    public function testToQueryKeepsSetFieldsAndOmitsNulls(): void
    {
        $query = (new Filters(
            browser: 'chrome',
            country: 'FR',
            eventType: 2,
            match: 'all',
        ))->toQuery();

        $this->assertEquals([
            'browser' => 'chrome',
            'country' => 'FR',
            'eventType' => 2,
            'match' => 'all',
        ], $query);
    }

    public function testEmptyFiltersProduceEmptyArray(): void
    {
        $this->assertSame([], (new Filters())->toQuery());
    }
}
