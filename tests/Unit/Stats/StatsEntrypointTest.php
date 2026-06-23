<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit\Stats;

use AdrienGras\Umami\Entrypoints\StatsEntrypoint;
use AdrienGras\Umami\Enums\MetricType;
use AdrienGras\Umami\Requests\Stats\GetStats;
use AdrienGras\Umami\Stats\Filters;
use AdrienGras\Umami\Stats\Period;
use AdrienGras\Umami\UmamiApi;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

final class StatsEntrypointTest extends TestCase
{
    /**
     * @param callable(StatsEntrypoint): mixed $call
     */
    private function capture(callable $call): PendingRequest
    {
        $captured = null;

        $api = new UmamiApi(baseUrl: 'http://umami.test', apiToken: 'tok');
        $api->withMockClient(new MockClient([
            function (PendingRequest $request) use (&$captured): MockResponse {
                $captured = $request;

                return MockResponse::make([], 200);
            },
        ]));

        $call($api->stats);

        if (!$captured instanceof PendingRequest) {
            self::fail('No request was sent.');
        }

        return $captured;
    }

    public function testStatsBuildsEndpointAndMergesPeriodAndFilters(): void
    {
        $pending = $this->capture(
            fn (StatsEntrypoint $s) => $s->stats('w-1', Period::between(1000, 2000), new Filters(browser: 'chrome')),
        );

        $this->assertInstanceOf(GetStats::class, $pending->getRequest());
        $this->assertStringContainsString('/api/websites/w-1/stats', $pending->getUrl());
        $this->assertEquals(
            ['startAt' => 1000, 'endAt' => 2000, 'browser' => 'chrome'],
            $pending->query()->all(),
        );
    }

    public function testMetricsIncludesTypeAndPaging(): void
    {
        $pending = $this->capture(
            fn (StatsEntrypoint $s) => $s->metrics('w-1', MetricType::Path, Period::between(1, 2), limit: 10, offset: 5),
        );

        $this->assertStringContainsString('/api/websites/w-1/metrics', $pending->getUrl());
        $this->assertEquals(
            ['startAt' => 1, 'endAt' => 2, 'type' => 'path', 'limit' => 10, 'offset' => 5],
            $pending->query()->all(),
        );
    }

    public function testEventsIncludesPagingAndSearch(): void
    {
        $pending = $this->capture(
            fn (StatsEntrypoint $s) => $s->events('w-1', Period::between(1, 2), page: 2, pageSize: 50, search: 'foo'),
        );

        $this->assertEquals(
            ['startAt' => 1, 'endAt' => 2, 'page' => 2, 'pageSize' => 50, 'search' => 'foo'],
            $pending->query()->all(),
        );
    }

    public function testActiveHasNoQuery(): void
    {
        $pending = $this->capture(fn (StatsEntrypoint $s) => $s->active('w-1'));

        $this->assertStringContainsString('/api/websites/w-1/active', $pending->getUrl());
        $this->assertSame([], $pending->query()->all());
    }

    public function testWebsiteIdMustNotBeEmpty(): void
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test');

        $this->expectException(InvalidArgumentException::class);

        $api->stats->stats('   ', Period::between(1, 2));
    }

    public function testConnectorExposesStatsEntrypoint(): void
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test');

        $this->assertInstanceOf(StatsEntrypoint::class, $api->stats);
    }
}
