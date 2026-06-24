<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit\Team;

use AdrienGras\Umami\Enums\TeamRole;
use AdrienGras\Umami\UmamiApi;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

final class TeamEntrypointTest extends TestCase
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

    public function testListBuildsQueryAgainstTeamsEndpoint(): void
    {
        $api = $this->apiCapturing(['data' => [], 'count' => 0]);

        $api->teams->list(page: 2, pageSize: 25);

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame(['page' => 2, 'pageSize' => 25], $pending->query()->all());
        self::assertSame('/api/teams', $pending->getRequest()->resolveEndpoint());
    }

    public function testListAllBuildsQueryAgainstAdminEndpoint(): void
    {
        $api = $this->apiCapturing(['data' => [], 'count' => 0]);

        $api->teams->listAll(search: 'ops');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame(['search' => 'ops'], $pending->query()->all());
        self::assertSame('/api/admin/teams', $pending->getRequest()->resolveEndpoint());
    }

    public function testGetHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['id' => 't1', 'name' => 'Ops']);

        $result = $api->teams->get('t1');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('Ops', $result['name']);
        self::assertSame('/api/teams/t1', $pending->getRequest()->resolveEndpoint());
    }

    public function testGetRejectsEmptyId(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->teams->get('  ');
    }

    public function testCreateBuildsBodyOmittingNullOwner(): void
    {
        // Live quirk: POST /api/teams returns a [team, ownerMembership] tuple.
        // create() must unwrap it and return the team object.
        $api = $this->apiCapturing([
            ['id' => 't1', 'name' => 'Ops', 'accessCode' => 'team_abc'],
            ['id' => 'tu1', 'userId' => 'u1', 'role' => 'team-owner'],
        ]);

        $result = $api->teams->create(name: 'Ops');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('t1', $result['id']);
        self::assertSame('team_abc', $result['accessCode']);
        $body = $pending->body();
        self::assertSame(['name' => 'Ops'], null === $body ? [] : $body->all());
        self::assertSame('/api/teams', $pending->getRequest()->resolveEndpoint());
    }

    public function testCreateIncludesOwnerId(): void
    {
        $api = $this->apiCapturing();

        $api->teams->create(name: 'Ops', ownerId: 'u1');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $body = $pending->body();
        self::assertSame(['name' => 'Ops', 'ownerId' => 'u1'], null === $body ? [] : $body->all());
    }

    public function testCreateRejectsEmptyName(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->teams->create(name: '  ');
    }

    public function testCreateRejectsTooLongName(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->teams->create(name: str_repeat('a', 51));
    }

    public function testUpdateBuildsBodyWithProvidedFieldsOnly(): void
    {
        $api = $this->apiCapturing(['id' => 't1']);

        $api->teams->update(id: 't1', name: 'Renamed', accessCode: 'team_newcode');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $body = $pending->body();
        self::assertSame(
            ['name' => 'Renamed', 'accessCode' => 'team_newcode'],
            null === $body ? [] : $body->all(),
        );
        self::assertSame('/api/teams/t1', $pending->getRequest()->resolveEndpoint());
    }

    public function testUpdateRejectsEmptyId(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->teams->update(id: '  ', name: 'x');
    }

    public function testUpdateRejectsTooLongName(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->teams->update(id: 't1', name: str_repeat('a', 51));
    }

    public function testDeleteReturnsVoidAndHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['ok' => true]);

        $api->teams->delete('t1');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('/api/teams/t1', $pending->getRequest()->resolveEndpoint());
    }

    public function testJoinBuildsBodyAndHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['id' => 'tu1', 'role' => 'team-member']);

        $result = $api->teams->join('team_abc123');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('team-member', $result['role']);
        $body = $pending->body();
        self::assertSame(['accessCode' => 'team_abc123'], null === $body ? [] : $body->all());
        self::assertSame('/api/teams/join', $pending->getRequest()->resolveEndpoint());
    }

    public function testJoinRejectsEmptyAccessCode(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->teams->join('  ');
    }

    public function testJoinRejectsTooLongAccessCode(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->teams->join(str_repeat('a', 51));
    }

    public function testMembersBuildsQueryAndHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['data' => [], 'count' => 0]);

        $api->teams->members('t1', page: 1, search: 'ali');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame(['page' => 1, 'search' => 'ali'], $pending->query()->all());
        self::assertSame('/api/teams/t1/users', $pending->getRequest()->resolveEndpoint());
    }

    public function testMemberHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['id' => 'tu1', 'userId' => 'u1', 'role' => 'team-manager']);

        $result = $api->teams->member('t1', 'u1');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('team-manager', $result['role']);
        self::assertSame('/api/teams/t1/users/u1', $pending->getRequest()->resolveEndpoint());
    }

    public function testAddMemberBuildsBodyAndHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['id' => 'tu1']);

        $api->teams->addMember('t1', 'u1', TeamRole::Manager);

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $body = $pending->body();
        self::assertSame(
            ['userId' => 'u1', 'role' => 'team-manager'],
            null === $body ? [] : $body->all(),
        );
        self::assertSame('/api/teams/t1/users', $pending->getRequest()->resolveEndpoint());
    }

    public function testAddMemberRejectsEmptyUserId(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->teams->addMember('t1', '  ', TeamRole::Member);
    }

    public function testUpdateMemberBuildsBodyAndHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['id' => 'tu1']);

        $api->teams->updateMember('t1', 'u1', TeamRole::ViewOnly);

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        $body = $pending->body();
        self::assertSame(['role' => 'team-view-only'], null === $body ? [] : $body->all());
        self::assertSame('/api/teams/t1/users/u1', $pending->getRequest()->resolveEndpoint());
    }

    public function testRemoveMemberReturnsVoidAndHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['ok' => true]);

        $api->teams->removeMember('t1', 'u1');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame('/api/teams/t1/users/u1', $pending->getRequest()->resolveEndpoint());
    }

    public function testWebsitesBuildsQueryAndHitsEndpoint(): void
    {
        $api = $this->apiCapturing(['data' => [], 'count' => 0]);

        $api->teams->websites('t1', pageSize: 50, search: 'shop');

        $pending = $api->getMockClient()?->getLastPendingRequest();
        self::assertInstanceOf(PendingRequest::class, $pending);
        self::assertSame(['pageSize' => 50, 'search' => 'shop'], $pending->query()->all());
        self::assertSame('/api/teams/t1/websites', $pending->getRequest()->resolveEndpoint());
    }

    public function testWebsitesRejectsEmptyId(): void
    {
        $api = $this->apiCapturing();

        $this->expectException(InvalidArgumentException::class);
        $api->teams->websites('  ');
    }
}
