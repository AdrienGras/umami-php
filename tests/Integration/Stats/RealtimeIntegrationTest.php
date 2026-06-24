<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Integration\Stats;

use AdrienGras\Umami\Tests\Integration\IntegrationTestCase;

/**
 * Integration test for StatsEntrypoint::realtime() against the docker instance.
 */
final class RealtimeIntegrationTest extends IntegrationTestCase
{
    public function testRealtimeReturnsExpectedShape(): void
    {
        $api = $this->connector($this->reportingToken());

        $realtime = $api->stats->realtime($this->websiteId);

        // Live shape: {countries, urls, referrers, events, series, totals, timestamp}.
        self::assertArrayHasKey('totals', $realtime);
        self::assertArrayHasKey('timestamp', $realtime);
        self::assertArrayHasKey('series', $realtime);
    }
}
