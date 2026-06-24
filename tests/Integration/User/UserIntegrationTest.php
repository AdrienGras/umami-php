<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Integration\User;

use AdrienGras\Umami\Enums\UserRole;
use AdrienGras\Umami\Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for UserEntrypoint against the real docker Umami instance.
 *
 * Requires .env.test (written by scripts/seed-umami.sh) and an admin token —
 * user management is admin-only server-side. The whole class is skipped when
 * the instance is not configured (IntegrationTestCase::setUp()).
 */
final class UserIntegrationTest extends IntegrationTestCase
{
    /**
     * Full CRUD cycle: create → get → update (role) → list → delete → gone from list.
     *
     * Never asserts on HTTP status alone — validates returned data fields.
     */
    public function testCrudCycle(): void
    {
        $api = $this->connector($this->reportingToken());

        // Unique username so leftover rows from a half-failed run never collide.
        $username = 'umami-php-u-' . (int) (microtime(true) * 1000);

        // Create — role echoed back as the lowercase enum value.
        $created = $api->users->create(username: $username, password: 'secret123', role: UserRole::User);
        self::assertArrayHasKey('id', $created);
        self::assertSame($username, $created['username']);
        self::assertSame('user', $created['role']);
        self::assertIsString($created['id']);
        $id = $created['id'];
        self::assertNotEmpty($id);

        // Get — assert fields, not status.
        $fetched = $api->users->get($id);
        self::assertSame($username, $fetched['username']);
        self::assertSame($id, $fetched['id']);

        // Update — change role, verify via re-get.
        $api->users->update($id, role: UserRole::ViewOnly);
        $updated = $api->users->get($id);
        self::assertSame('view-only', $updated['role']);

        // List (admin) — our user must be present.
        $list = $api->users->list(pageSize: 100);
        self::assertArrayHasKey('data', $list);
        self::assertIsArray($list['data']);
        $ids = array_column($list['data'], 'id');
        self::assertContains($id, $ids, 'Created user must appear in list()');

        // Delete — no exception = success.
        $api->users->delete($id);

        // Gone from list — confirms the delete without relying on GET-after-delete
        // behaviour (which differs across resources).
        $listAfter = $api->users->list(pageSize: 100);
        self::assertIsArray($listAfter['data']);
        $idsAfter = array_column($listAfter['data'], 'id');
        self::assertNotContains($id, $idsAfter, 'Deleted user must not appear in list()');
    }

    /**
     * websites() sub-route lists the websites owned by a user.
     *
     * Self-consistent dogfood: the seeded website's owner id must, in turn,
     * own that website. Confirms the real `/api/users/{id}/websites` shape.
     */
    public function testWebsitesSubRouteListsOwnedWebsite(): void
    {
        $api = $this->connector($this->reportingToken());

        $site = $api->websites->get($this->websiteId);
        self::assertArrayHasKey('userId', $site);
        self::assertIsString($site['userId']);
        $ownerId = $site['userId'];

        $result = $api->users->websites($ownerId, pageSize: 100);
        self::assertArrayHasKey('data', $result);
        self::assertIsArray($result['data']);
        $ids = array_column($result['data'], 'id');
        self::assertContains($this->websiteId, $ids, 'Owner must list the seeded website');
    }

    /**
     * teams() sub-route returns the paginated shape (data may be empty).
     */
    public function testTeamsSubRouteReturnsPaginatedShape(): void
    {
        $api = $this->connector($this->reportingToken());

        $site = $api->websites->get($this->websiteId);
        self::assertIsString($site['userId']);
        $ownerId = $site['userId'];

        $result = $api->users->teams($ownerId, pageSize: 10);
        self::assertArrayHasKey('data', $result);
        self::assertIsArray($result['data']);
        self::assertArrayHasKey('count', $result);
    }
}
