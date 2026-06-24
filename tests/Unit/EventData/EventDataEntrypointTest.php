<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit\EventData;

use AdrienGras\Umami\Stats\Filters;
use AdrienGras\Umami\Stats\Period;
use AdrienGras\Umami\UmamiApi;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

final class EventDataEntrypointTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $responseBody
     */
    private function apiCapturing(array $responseBody = []): UmamiApi
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test', apiToken: 'tok');
        $api->withMockClient(new MockClient([
            function (PendingRequest $request) use ($responseBody): MockResponse {
                return MockResponse::make($responseBody, 200);
            },
        ]));

        return $api;
    }

    public function testListBuildsQueryAndHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['data' => [], 'count' => 0]);

        $api->eventData->list('w1', Period::between(1000, 2000), new Filters(country: 'FR'), page: 1);

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $query = $pending->query()->all();
        self::assertSame(1000, $query['startAt']);
        self::assertSame(2000, $query['endAt']);
        self::assertSame('FR', $query['country']);
        self::assertSame(1, $query['page']);
        self::assertSame('/api/websites/w1/event-data', $pending->getRequest()->resolveEndpoint());
    }

    public function testListRejectsEmptyWebsiteId(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->eventData->list('  ', Period::between(1000, 2000));
    }

    public function testGetHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['eventId' => 'e1', 'eventName' => 'probe']);

        $result = $api->eventData->get('w1', 'e1');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('probe', $result['eventName']);
        self::assertSame('/api/websites/w1/event-data/e1', $pending->getRequest()->resolveEndpoint());
    }

    public function testGetRejectsEmptyEventId(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->eventData->get('w1', '  ');
    }

    public function testEventsRequiresEventAndHitsEndpoint(): void
    {
        $api = $this->apiCapturing([]);

        $api->eventData->events('w1', Period::between(1000, 2000), 'probe_event');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $query = $pending->query()->all();
        self::assertSame('probe_event', $query['event']);
        self::assertSame(1000, $query['startAt']);
        self::assertSame('/api/websites/w1/event-data/events', $pending->getRequest()->resolveEndpoint());
    }

    public function testEventsRejectsEmptyEvent(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->eventData->events('w1', Period::between(1000, 2000), '  ');
    }

    public function testFieldsHitsEndpoint(): void
    {
        $api = $this->apiCapturing([]);

        $api->eventData->fields('w1', Period::between(1000, 2000));

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('/api/websites/w1/event-data/fields', $pending->getRequest()->resolveEndpoint());
    }

    public function testPropertiesHitsEndpoint(): void
    {
        $api = $this->apiCapturing([]);

        $api->eventData->properties('w1', Period::between(1000, 2000));

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('/api/websites/w1/event-data/properties', $pending->getRequest()->resolveEndpoint());
    }

    public function testValuesBuildsQueryWithEventAndProperty(): void
    {
        $api = $this->apiCapturing([]);

        $api->eventData->values('w1', Period::between(1000, 2000), 'probe_event', 'color');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $query = $pending->query()->all();
        self::assertSame('probe_event', $query['event']);
        self::assertSame('color', $query['propertyName']);
        self::assertSame('/api/websites/w1/event-data/values', $pending->getRequest()->resolveEndpoint());
    }

    public function testValuesRejectsEmptyPropertyName(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->eventData->values('w1', Period::between(1000, 2000), 'ev', '  ');
    }

    public function testStatsHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['events' => 1, 'properties' => 2]);

        $result = $api->eventData->stats('w1', Period::between(1000, 2000));

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertArrayHasKey('events', $result);
        self::assertSame('/api/websites/w1/event-data/stats', $pending->getRequest()->resolveEndpoint());
    }
}
