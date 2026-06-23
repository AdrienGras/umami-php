<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Integration\Website;

use AdrienGras\Umami\Stats\Period;
use AdrienGras\Umami\Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for WebsiteEntrypoint against the real docker Umami instance.
 *
 * Requires .env.test (written by scripts/seed-umami.sh). The entire test class
 * is skipped when the instance is not configured (IntegrationTestCase::setUp()).
 */
final class WebsiteIntegrationTest extends IntegrationTestCase
{
    /**
     * Full CRUD cycle: create → get → update → list → reset → delete → get throws.
     *
     * Never asserts on HTTP status alone — validates returned data fields.
     */
    public function testCrudCycle(): void
    {
        $api = $this->connector($this->reportingToken());

        // Create
        $created = $api->websites->create(name: 'umami-php-crud', domain: 'crud.umami-php.test');
        self::assertArrayHasKey('id', $created);
        self::assertArrayHasKey('name', $created);
        self::assertSame('umami-php-crud', $created['name']);
        self::assertArrayHasKey('domain', $created);
        self::assertSame('crud.umami-php.test', $created['domain']);

        self::assertIsString($created['id']);
        $id = $created['id'];
        self::assertNotEmpty($id);

        // Get — assert fields, not status
        $fetched = $api->websites->get($id);
        self::assertSame('umami-php-crud', $fetched['name']);
        self::assertSame('crud.umami-php.test', $fetched['domain']);
        self::assertSame($id, $fetched['id']);

        // Update — change name, verify via re-get
        $api->websites->update($id, name: 'umami-php-crud-updated');
        $updated = $api->websites->get($id);
        self::assertSame('umami-php-crud-updated', $updated['name']);
        // domain unchanged
        self::assertSame('crud.umami-php.test', $updated['domain']);

        // List — confirm our website id is present in data
        $list = $api->websites->list(pageSize: 100);
        self::assertArrayHasKey('data', $list);
        $data = $list['data'];
        self::assertIsArray($data);
        $ids = array_column($data, 'id');
        self::assertContains($id, $ids, 'Created website must appear in list()');

        // Reset — wipes analytics data, returns nothing. reset() returns void — no exception is the success signal.
        $api->websites->reset($id);

        // Delete — no exception = success
        $api->websites->delete($id);

        // Note: GET on a deleted website returns HTTP 200 + body null (Umami quirk, confirmed live).
        // Saloon throws TypeError when assigning null to array $decodedJson — not our exception.
        // We confirm delete succeeded by verifying the id is gone from list() instead.
        $listAfter = $api->websites->list(pageSize: 100);
        $dataAfter = $listAfter['data'];
        $idsAfter = is_array($dataAfter) ? array_column($dataAfter, 'id') : [];
        self::assertNotContains($id, $idsAfter, 'Deleted website must not appear in list()');
    }

    /**
     * dateRange on the seeded website returns an array with date keys.
     *
     * Uses a wide Period window (±30 days) so the seeded website's data is in range.
     * Real live shape: {startDate: ISO-string, endDate: ISO-string} (API_UMAMI.md initially documented {mindate,maxdate} — corrected at étape 7.4).
     */
    public function testDateRangeOnSeededWebsite(): void
    {
        $api = $this->connector($this->reportingToken());

        $range = $api->websites->dateRange($this->websiteId);

        // Real shape confirmed live (étape 7.4): {startDate: ISO-string, endDate: ISO-string}
        // Note: API_UMAMI.md initially documented {mindate, maxdate} — corrected here.
        self::assertArrayHasKey('startDate', $range, 'dateRange must contain startDate key');
        self::assertArrayHasKey('endDate', $range, 'dateRange must contain endDate key');
    }

    /**
     * values() on the seeded website returns a list (possibly empty) for 'browser' type.
     *
     * Uses a wide Period (30 days back → now) to maximise the chance of getting results.
     * The real shape is: [{value: string}] — lifted ⚠ live marker.
     */
    public function testValuesOnSeededWebsite(): void
    {
        $api = $this->connector($this->reportingToken());

        $now = (int) (microtime(true) * 1000);
        $thirtyDaysAgo = $now - (30 * 24 * 3_600_000);
        $period = Period::between($thirtyDaysAgo, $now);

        // 'browser' is a SESSION_COLUMNS field — common and likely to have data
        $values = $api->websites->values($this->websiteId, 'browser', $period);

        // Real shape confirmed live (étape 7.4): [{value: string, count: int}]
        // Note: API_UMAMI.md documented [{value}] only — count is also present.
        self::assertIsList($values);
        foreach ($values as $item) {
            self::assertIsArray($item);
            self::assertArrayHasKey('value', $item, 'Each values() item must have a value key');
            self::assertArrayHasKey('count', $item, 'Each values() item must have a count key');
        }
    }

    /**
     * values() with 'path' field type also works (EVENT_COLUMNS member).
     *
     * Smoke test: verifies the 'path' type is accepted (no 400/404). Item shape is validated in testValuesOnSeededWebsite.
     */
    public function testValuesForPathType(): void
    {
        $api = $this->connector($this->reportingToken());

        $now = (int) (microtime(true) * 1000);
        $period = Period::between($now - 3_600_000, $now);

        $values = $api->websites->values($this->websiteId, 'path', $period);

        self::assertIsList($values);
    }
}
