<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Integration\Stats;

use AdrienGras\Umami\Stats\Period;
use AdrienGras\Umami\Tests\Integration\IntegrationTestCase;

final class StatsIntegrationTest extends IntegrationTestCase
{
    private const BROWSER_UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    public function testStatsReturnsAggregatesAfterAHit(): void
    {
        $path = '/it-stats-' . uniqid();

        $this->connector()->tracking->pageview(
            $this->websiteId,
            url: $path,
            hostname: $this->hostname,
            userAgent: self::BROWSER_UA,
        );
        $this->assertTrue($this->waitForPath($path), 'precondition: the hit must be recorded');

        $now = (int) (microtime(true) * 1000);
        $stats = $this->connector($this->reportingToken())->stats->stats(
            $this->websiteId,
            Period::between($now - 3_600_000, $now + 3_600_000),
        );

        $this->assertArrayHasKey('pageviews', $stats);
        $this->assertGreaterThanOrEqual(1, $stats['pageviews']);
        $this->assertArrayHasKey('visitors', $stats);
    }

    public function testMetricsByPathContainsTheHit(): void
    {
        $path = '/it-metrics-' . uniqid();

        $this->connector()->tracking->pageview(
            $this->websiteId,
            url: $path,
            hostname: $this->hostname,
            userAgent: self::BROWSER_UA,
        );

        // recordedPaths() dogfoods stats->metrics(type=path).
        $this->assertTrue($this->waitForPath($path));
    }

    public function testActiveVisitorsReturnsAList(): void
    {
        $active = $this->connector($this->reportingToken())->stats->active($this->websiteId);

        // Shape is a list (possibly empty); the call must authenticate and parse.
        $this->assertIsList($active);
    }
}
