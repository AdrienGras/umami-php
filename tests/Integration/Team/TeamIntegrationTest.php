<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Integration\Team;

use AdrienGras\Umami\Enums\TeamRole;
use AdrienGras\Umami\Enums\UserRole;
use AdrienGras\Umami\Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for TeamEntrypoint against the real docker Umami instance.
 *
 * Requires .env.test (written by scripts/seed-umami.sh) and an admin token.
 * The whole class is skipped when the instance is not configured
 * (IntegrationTestCase::setUp()).
 */
final class TeamIntegrationTest extends IntegrationTestCase
{
    /**
     * Team CRUD + membership management, all driven by the seeded admin (who is
     * the team owner). create → get → update → list/listAll → add/get/update/remove
     * member → websites → delete. Validates returned data, never status alone.
     */
    public function testCrudAndMembershipCycle(): void
    {
        $api = $this->connector($this->reportingToken());
        $stamp = (int) (microtime(true) * 1000);

        // Create — admin becomes team-owner. accessCode is generated server-side.
        $created = $api->teams->create(name: 'umami-php-team-' . $stamp);
        self::assertArrayHasKey('id', $created);
        self::assertIsString($created['id']);
        $teamId = $created['id'];
        self::assertNotEmpty($teamId);
        self::assertSame('umami-php-team-' . $stamp, $created['name']);

        // Get — assert fields, not status.
        $fetched = $api->teams->get($teamId);
        self::assertSame($teamId, $fetched['id']);
        self::assertSame('umami-php-team-' . $stamp, $fetched['name']);

        // Update — rename, verify via re-get.
        $api->teams->update($teamId, name: 'umami-php-team-renamed-' . $stamp);
        $updated = $api->teams->get($teamId);
        self::assertSame('umami-php-team-renamed-' . $stamp, $updated['name']);

        // list() — the owner's teams must include it.
        $list = $api->teams->list(pageSize: 100);
        self::assertIsArray($list['data']);
        self::assertContains($teamId, array_column($list['data'], 'id'), 'Owned team must appear in list()');

        // listAll() — admin sees it too.
        $listAll = $api->teams->listAll(pageSize: 100, search: 'umami-php-team-renamed-' . $stamp);
        self::assertIsArray($listAll['data']);
        self::assertContains($teamId, array_column($listAll['data'], 'id'), 'Team must appear in admin listAll()');

        // Membership — need a real user to add.
        $member = $api->users->create(
            username: 'umami-php-tm-' . $stamp,
            password: 'secret123',
            role: UserRole::User,
        );
        self::assertIsString($member['id']);
        $memberId = $member['id'];

        // addMember — role echoed back lowercase.
        $added = $api->teams->addMember($teamId, $memberId, TeamRole::Member);
        self::assertSame('team-member', $added['role']);

        // member — single membership reflects the role.
        $membership = $api->teams->member($teamId, $memberId);
        self::assertSame($memberId, $membership['userId']);
        self::assertSame('team-member', $membership['role']);

        // updateMember — promote to manager, verify via re-get.
        $api->teams->updateMember($teamId, $memberId, TeamRole::Manager);
        self::assertSame('team-manager', $api->teams->member($teamId, $memberId)['role']);

        // members — list contains both owner and the new member.
        $members = $api->teams->members($teamId, pageSize: 100);
        self::assertIsArray($members['data']);
        $memberUserIds = array_column($members['data'], 'userId');
        self::assertContains($memberId, $memberUserIds, 'Added member must appear in members()');

        // removeMember — no exception = success; gone from members().
        $api->teams->removeMember($teamId, $memberId);
        $membersAfter = $api->teams->members($teamId, pageSize: 100);
        self::assertIsArray($membersAfter['data']);
        self::assertNotContains(
            $memberId,
            array_column($membersAfter['data'], 'userId'),
            'Removed member must not appear in members()',
        );

        // websites — paginated shape (likely empty for a fresh team).
        $websites = $api->teams->websites($teamId, pageSize: 10);
        self::assertArrayHasKey('data', $websites);
        self::assertIsArray($websites['data']);
        self::assertArrayHasKey('count', $websites);

        // Cleanup.
        $api->users->delete($memberId);
        $api->teams->delete($teamId);

        // Team gone from list().
        $listFinal = $api->teams->list(pageSize: 100);
        self::assertIsArray($listFinal['data']);
        self::assertNotContains($teamId, array_column($listFinal['data'], 'id'), 'Deleted team must not appear in list()');
    }

    /**
     * join() flow end-to-end: admin creates a team, a freshly created user logs
     * in and joins via the access code, then the admin confirms the membership.
     */
    public function testJoinByAccessCode(): void
    {
        $admin = $this->connector($this->reportingToken());
        $stamp = (int) (microtime(true) * 1000);

        $team = $admin->teams->create(name: 'umami-php-join-' . $stamp);
        self::assertIsString($team['id']);
        $teamId = $team['id'];
        self::assertArrayHasKey('accessCode', $team, 'Created team must expose its accessCode');
        self::assertIsString($team['accessCode']);
        $accessCode = $team['accessCode'];

        $username = 'umami-php-joiner-' . $stamp;
        $password = 'secret123';
        $user = $admin->users->create(username: $username, password: $password, role: UserRole::User);
        self::assertIsString($user['id']);
        $userId = $user['id'];

        // The joiner authenticates with its own connector/token.
        $joiner = $this->connector();
        $joiner->auth->login($username, $password);

        $joined = $joiner->teams->join($accessCode);
        self::assertSame('team-member', $joined['role']);
        self::assertSame($userId, $joined['userId']);

        // Admin confirms the membership landed.
        $members = $admin->teams->members($teamId, pageSize: 100);
        self::assertIsArray($members['data']);
        self::assertContains($userId, array_column($members['data'], 'userId'), 'Joined user must be a member');

        // Cleanup.
        $admin->users->delete($userId);
        $admin->teams->delete($teamId);
    }
}
