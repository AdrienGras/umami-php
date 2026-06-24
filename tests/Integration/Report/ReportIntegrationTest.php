<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Integration\Report;

use AdrienGras\Umami\Enums\ReportType;
use AdrienGras\Umami\Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for ReportEntrypoint against the real docker Umami instance.
 *
 * Requires .env.test (written by scripts/seed-umami.sh) and an admin token.
 * The whole class is skipped when the instance is not configured.
 */
final class ReportIntegrationTest extends IntegrationTestCase
{
    /**
     * Saved-report CRUD cycle: create → get → update → list → delete → gone.
     */
    public function testSavedReportCrudCycle(): void
    {
        $api = $this->connector($this->reportingToken());
        $stamp = (int) (microtime(true) * 1000);
        $name = 'umami-php-report-' . $stamp;

        $parameters = [
            'window' => 3_600_000,
            'steps' => [
                ['type' => 'path', 'value' => '/'],
                ['type' => 'path', 'value' => '/about'],
            ],
        ];

        // Create — saved report stores its parameters verbatim.
        $created = $api->reports->create($this->websiteId, ReportType::Funnel, $name, $parameters);
        self::assertArrayHasKey('id', $created);
        self::assertIsString($created['id']);
        $id = $created['id'];
        self::assertNotEmpty($id);
        self::assertSame($name, $created['name']);
        self::assertSame('funnel', $created['type']);

        // Get — fields, not status.
        $fetched = $api->reports->get($id);
        self::assertSame($id, $fetched['id']);
        self::assertSame($name, $fetched['name']);

        // Update — rename, verify via re-get.
        $api->reports->update($id, $this->websiteId, ReportType::Funnel, $name . '-upd', $parameters);
        self::assertSame($name . '-upd', $api->reports->get($id)['name']);

        // List (filtered by type) — must contain our report.
        $list = $api->reports->list($this->websiteId, ReportType::Funnel, pageSize: 100);
        self::assertArrayHasKey('data', $list);
        self::assertIsArray($list['data']);
        self::assertContains($id, array_column($list['data'], 'id'), 'Created report must appear in list()');

        // Delete — no exception = success; gone from list.
        $api->reports->delete($id);
        $listAfter = $api->reports->list($this->websiteId, ReportType::Funnel, pageSize: 100);
        self::assertIsArray($listAfter['data']);
        self::assertNotContains($id, array_column($listAfter['data'], 'id'), 'Deleted report must not appear in list()');
    }

    /**
     * utm generation returns an object keyed by the five UTM dimensions.
     */
    public function testUtmGenerationReturnsObjectShape(): void
    {
        $api = $this->connector($this->reportingToken());

        $result = $api->reports->utm($this->websiteId, [
            'startDate' => date('Y-m-d', strtotime('-30 days')),
            'endDate' => date('Y-m-d'),
        ]);

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
            self::assertArrayHasKey($key, $result, "utm result must contain {$key}");
        }
    }

    /**
     * funnel generation returns a LIST (one entry per step), not an object —
     * confirms the native-shape passthrough (asArray).
     */
    public function testFunnelGenerationReturnsListShape(): void
    {
        $api = $this->connector($this->reportingToken());

        $result = $api->reports->funnel($this->websiteId, [
            'startDate' => date('Y-m-d', strtotime('-30 days')),
            'endDate' => date('Y-m-d'),
            'window' => 3_600_000,
            'steps' => [
                ['type' => 'path', 'value' => '/'],
                ['type' => 'path', 'value' => '/about'],
            ],
        ]);

        self::assertIsList($result);
        self::assertCount(2, $result, 'funnel returns one entry per step');
        foreach ($result as $step) {
            self::assertIsArray($step);
            self::assertArrayHasKey('type', $step);
            self::assertArrayHasKey('value', $step);
            self::assertArrayHasKey('visitors', $step);
        }
    }
}
