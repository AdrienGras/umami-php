<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit\Tracking;

use AdrienGras\Umami\Tracking\Payload;
use PHPUnit\Framework\TestCase;

final class PayloadTest extends TestCase
{
    public function testToArrayKeepsSetFieldsAndOmitsNulls(): void
    {
        $payload = new Payload(
            website: 'w-1',
            url: '/home',
            title: 'Home',
            data: ['plan' => 'pro'],
            timestamp: 1700000000,
        );

        $this->assertEquals([
            'website' => 'w-1',
            'url' => '/home',
            'title' => 'Home',
            'data' => ['plan' => 'pro'],
            'timestamp' => 1700000000,
        ], $payload->toArray());
    }

    public function testEmptyPayloadProducesEmptyArray(): void
    {
        $this->assertSame([], (new Payload())->toArray());
    }

    public function testWebVitalsAndDistinctIdAreCarried(): void
    {
        $payload = new Payload(website: 'w-1', id: 'user-42', lcp: 1234.5);

        $this->assertEquals(
            ['website' => 'w-1', 'id' => 'user-42', 'lcp' => 1234.5],
            $payload->toArray(),
        );
    }
}
