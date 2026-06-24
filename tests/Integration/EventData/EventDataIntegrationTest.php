<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Integration\EventData;

use AdrienGras\Umami\Stats\Period;
use AdrienGras\Umami\Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for EventDataEntrypoint against the docker Umami instance.
 *
 * Records a custom event (dogfood via TrackingEntrypoint), waits for it to be
 * indexed, then exercises every event-data endpoint and asserts real shapes.
 */
final class EventDataIntegrationTest extends IntegrationTestCase
{
    private const BROWSER_UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    public function testEventDataLifecycle(): void
    {
        $api = $this->connector($this->reportingToken());
        $eventName = 'umami_php_evt_' . (int) (microtime(true) * 1000);

        // Record a custom event with a string property (tracking is skipAuth).
        $this->connector()->tracking->event(
            websiteId: $this->websiteId,
            name: $eventName,
            data: ['color' => 'blue'],
            userAgent: self::BROWSER_UA,
        );

        $now = (int) (microtime(true) * 1000);
        $period = Period::between($now - 3_600_000, $now + 3_600_000);

        // Wait until the event is indexed in event-data.
        $found = false;
        $deadline = microtime(true) + 15.0;
        do {
            $list = $api->eventData->list($this->websiteId, $period, pageSize: 100);
            self::assertArrayHasKey('data', $list);
            self::assertIsArray($list['data']);
            if (in_array($eventName, array_column($list['data'], 'eventName'), true)) {
                $found = true;
                break;
            }
            usleep(700_000);
        } while (microtime(true) < $deadline);

        self::assertTrue($found, 'Recorded event must appear in event-data list()');

        // events(): property breakdown for the event — list of rows.
        $events = $api->eventData->events($this->websiteId, $period, $eventName);
        self::assertIsList($events);
        self::assertNotEmpty($events, 'events() must return rows for an event with properties');
        foreach ($events as $row) {
            self::assertIsArray($row);
            self::assertArrayHasKey('propertyName', $row);
            self::assertArrayHasKey('propertyValue', $row);
        }

        // values(): distinct values of the 'color' property.
        $values = $api->eventData->values($this->websiteId, $period, $eventName, 'color');
        self::assertIsList($values);
        self::assertContains('blue', array_column($values, 'value'), 'color values must include blue');

        // properties(): list of property names.
        self::assertIsList($api->eventData->properties($this->websiteId, $period));

        // fields(): list (may be empty).
        self::assertIsList($api->eventData->fields($this->websiteId, $period));

        // stats(): aggregate object.
        $stats = $api->eventData->stats($this->websiteId, $period);
        self::assertArrayHasKey('events', $stats);
        self::assertArrayHasKey('properties', $stats);
    }
}
