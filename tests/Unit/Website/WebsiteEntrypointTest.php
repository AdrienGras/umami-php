<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit\Website;

use AdrienGras\Umami\Enums\MaskLevel;
use AdrienGras\Umami\Stats\Period;
use AdrienGras\Umami\UmamiApi;
use AdrienGras\Umami\Website\ReplayConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

final class WebsiteEntrypointTest extends TestCase
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

    public function testCreateBuildsBodyOmittingNulls(): void
    {
        $api = $this->apiCapturing(['id' => 'w1', 'name' => 'Site']);

        $result = $api->websites->create(name: 'Site', domain: 'example.com');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('w1', $result['id']);
        $body = $pending->body();
        self::assertSame(['name' => 'Site', 'domain' => 'example.com'], null === $body ? [] : $body->all());
        self::assertSame('/api/websites', $pending->getRequest()->resolveEndpoint());
    }

    public function testCreateIncludesOptionalFields(): void
    {
        $api = $this->apiCapturing();

        $api->websites->create(name: 'S', domain: 'd.com', shareId: 'sh', teamId: 't1', id: 'fixed');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $body = $pending->body();
        self::assertSame(
            ['name' => 'S', 'domain' => 'd.com', 'shareId' => 'sh', 'teamId' => 't1', 'id' => 'fixed'],
            null === $body ? [] : $body->all(),
        );
    }

    public function testCreateRejectsEmptyName(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->websites->create(name: '  ', domain: 'd.com');
    }

    public function testCreateRejectsTooLongDomain(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->websites->create(name: 'S', domain: str_repeat('a', 501));
    }

    public function testUpdateSerializesReplayConfig(): void
    {
        $api = $this->apiCapturing();

        $api->websites->update(
            id: 'w1',
            name: 'New',
            replayEnabled: true,
            replayConfig: new ReplayConfig(sampleRate: 0.5, maskLevel: MaskLevel::Strict),
        );

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $body = $pending->body();
        self::assertSame(
            [
                'name' => 'New',
                'replayEnabled' => true,
                'replayConfig' => ['sampleRate' => 0.5, 'maskLevel' => 'strict'],
            ],
            null === $body ? [] : $body->all(),
        );
        self::assertSame('/api/websites/w1', $pending->getRequest()->resolveEndpoint());
    }

    public function testListBuildsQueryOmittingNulls(): void
    {
        $api = $this->apiCapturing(['data' => [], 'count' => 0]);

        $api->websites->list(page: 2, search: 'foo');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame(['page' => 2, 'search' => 'foo'], $pending->query()->all());
    }

    public function testGetRejectsEmptyId(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->websites->get('  ');
    }

    public function testDeleteReturnsVoidAndHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['ok' => true]);

        $api->websites->delete('w1');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('/api/websites/w1', $pending->getRequest()->resolveEndpoint());
    }

    public function testResetHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['ok' => true]);

        $api->websites->reset('w1');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('/api/websites/w1/reset', $pending->getRequest()->resolveEndpoint());
    }

    public function testTransferToUserBuildsBody(): void
    {
        $api = $this->apiCapturing(['id' => 'w1']);

        $api->websites->transfer('w1', userId: 'u1');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $body = $pending->body();
        self::assertSame(['userId' => 'u1'], null === $body ? [] : $body->all());
        self::assertSame('/api/websites/w1/transfer', $pending->getRequest()->resolveEndpoint());
    }

    public function testTransferToTeamBuildsBody(): void
    {
        $api = $this->apiCapturing(['id' => 'w1']);

        $api->websites->transfer('w1', teamId: 't1');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $body = $pending->body();
        self::assertSame(['teamId' => 't1'], null === $body ? [] : $body->all());
    }

    public function testTransferRejectsNoTarget(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->websites->transfer('w1');
    }

    public function testTransferRejectsBothTargets(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->websites->transfer('w1', userId: 'u1', teamId: 't1');
    }

    public function testDateRangeReturnsObject(): void
    {
        $api = $this->apiCapturing(['mindate' => '2026-01-01', 'maxdate' => '2026-06-01']);

        $result = $api->websites->dateRange('w1');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('2026-01-01', $result['mindate']);
        self::assertSame('/api/websites/w1/daterange', $pending->getRequest()->resolveEndpoint());
    }

    public function testValuesBuildsQueryAndReturnsList(): void
    {
        $api = $this->apiCapturing([['value' => '/home'], ['value' => '/about']]);

        $result = $api->websites->values('w1', 'path', Period::between(1000, 2000), search: 'ho');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame([['value' => '/home'], ['value' => '/about']], $result);
        $query = $pending->query()->all();
        self::assertSame('path', $query['type']);
        self::assertSame('ho', $query['search']);
        self::assertSame(1000, $query['startAt']);
        self::assertSame(2000, $query['endAt']);
        self::assertSame('/api/websites/w1/values', $pending->getRequest()->resolveEndpoint());
    }

    public function testValuesRejectsEmptyType(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->websites->values('w1', '  ', Period::between(1000, 2000));
    }
}
