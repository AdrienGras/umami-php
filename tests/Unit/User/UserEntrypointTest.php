<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit\User;

use AdrienGras\Umami\Enums\UserRole;
use AdrienGras\Umami\UmamiApi;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

final class UserEntrypointTest extends TestCase
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

    public function testCreateBuildsBodyOmittingNullId(): void
    {
        $api = $this->apiCapturing(['id' => 'u1', 'username' => 'alice']);

        $result = $api->users->create(username: 'alice', password: 'secret12', role: UserRole::User);

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('u1', $result['id']);
        $body = $pending->body();
        self::assertSame(
            ['username' => 'alice', 'password' => 'secret12', 'role' => 'user'],
            null === $body ? [] : $body->all(),
        );
        self::assertSame('/api/users', $pending->getRequest()->resolveEndpoint());
    }

    public function testCreateIncludesIdAndSerializesRole(): void
    {
        $api = $this->apiCapturing();

        $api->users->create(username: 'bob', password: 'secret12', role: UserRole::ViewOnly, id: 'fixed');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $body = $pending->body();
        self::assertSame(
            ['username' => 'bob', 'password' => 'secret12', 'role' => 'view-only', 'id' => 'fixed'],
            null === $body ? [] : $body->all(),
        );
    }

    public function testCreateRejectsEmptyUsername(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->users->create(username: '  ', password: 'secret12', role: UserRole::User);
    }

    public function testCreateRejectsTooLongUsername(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->users->create(username: str_repeat('a', 256), password: 'secret12', role: UserRole::User);
    }

    public function testCreateRejectsShortPassword(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->users->create(username: 'alice', password: 'short', role: UserRole::User);
    }

    public function testCreateRejectsTooLongPassword(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->users->create(username: 'alice', password: str_repeat('a', 256), role: UserRole::User);
    }

    public function testListBuildsQueryOmittingNullsAgainstAdminEndpoint(): void
    {
        $api = $this->apiCapturing(['data' => [], 'count' => 0]);

        $api->users->list(page: 2, search: 'ali');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame(['page' => 2, 'search' => 'ali'], $pending->query()->all());
        self::assertSame('/api/admin/users', $pending->getRequest()->resolveEndpoint());
    }

    public function testGetHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['id' => 'u1', 'username' => 'alice']);

        $result = $api->users->get('u1');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('alice', $result['username']);
        self::assertSame('/api/users/u1', $pending->getRequest()->resolveEndpoint());
    }

    public function testGetRejectsEmptyId(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->users->get('  ');
    }

    public function testUpdateBuildsBodyWithProvidedFieldsOnly(): void
    {
        $api = $this->apiCapturing(['id' => 'u1']);

        $api->users->update(id: 'u1', username: 'newname', role: UserRole::Admin);

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $body = $pending->body();
        self::assertSame(
            ['username' => 'newname', 'role' => 'admin'],
            null === $body ? [] : $body->all(),
        );
        self::assertSame('/api/users/u1', $pending->getRequest()->resolveEndpoint());
    }

    public function testUpdateRejectsEmptyId(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->users->update(id: '  ', username: 'x');
    }

    public function testUpdateRejectsShortPassword(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->users->update(id: 'u1', password: 'short');
    }

    public function testDeleteReturnsVoidAndHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['ok' => true]);

        $api->users->delete('u1');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('/api/users/u1', $pending->getRequest()->resolveEndpoint());
    }

    public function testTeamsBuildsQueryAndHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['data' => [], 'count' => 0]);

        $api->users->teams('u1', page: 1, pageSize: 50);

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame(['page' => 1, 'pageSize' => 50], $pending->query()->all());
        self::assertSame('/api/users/u1/teams', $pending->getRequest()->resolveEndpoint());
    }

    public function testWebsitesBuildsQueryWithIncludeTeamsAndHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['data' => [], 'count' => 0]);

        $api->users->websites('u1', search: 'shop', includeTeams: true);

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame(['search' => 'shop', 'includeTeams' => true], $pending->query()->all());
        self::assertSame('/api/users/u1/websites', $pending->getRequest()->resolveEndpoint());
    }

    public function testWebsitesRejectsEmptyId(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->users->websites('  ');
    }
}
