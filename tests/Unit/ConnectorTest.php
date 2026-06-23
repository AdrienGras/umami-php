<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit;

use AdrienGras\Umami\Entrypoints\TrackingEntrypoint;
use AdrienGras\Umami\UmamiApi;
use PHPUnit\Framework\TestCase;

final class ConnectorTest extends TestCase
{
    public function testExposesTrackingEntrypoint(): void
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test');

        $this->assertInstanceOf(TrackingEntrypoint::class, $api->tracking);
    }
}
