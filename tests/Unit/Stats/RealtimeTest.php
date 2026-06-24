<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit\Stats;

use AdrienGras\Umami\UmamiApi;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

final class RealtimeTest extends TestCase
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

    public function testRealtimeHitsDedicatedEndpoint(): void
    {
        $api = $this->apiCapturing(['totals' => ['views' => 5], 'timestamp' => 1]);

        $result = $api->stats->realtime('w1');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertArrayHasKey('totals', $result);
        self::assertSame('/api/realtime/w1', $pending->getRequest()->resolveEndpoint());
    }

    public function testRealtimeRejectsEmptyWebsiteId(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->stats->realtime('  ');
    }
}
