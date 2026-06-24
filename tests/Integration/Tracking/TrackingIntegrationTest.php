<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Integration\Tracking;

use AdrienGras\Umami\Exceptions\BotFilteredException;
use AdrienGras\Umami\Tests\Integration\IntegrationTestCase;

final class TrackingIntegrationTest extends IntegrationTestCase
{
    private const BROWSER_UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    public function testPageviewWithVisitorUserAgentIsRecorded(): void
    {
        $path = '/it-human-' . uniqid();

        $this->connector()->tracking->pageview(
            $this->websiteId,
            url: $path,
            title: 'IT human',
            hostname: $this->hostname,
            userAgent: self::BROWSER_UA,
        );

        $this->assertTrue(
            $this->waitForPath($path),
            "Pageview {$path} should appear in the stats.",
        );
    }

    public function testBotUserAgentRaisesBotFilteredExceptionAndIsNotRecorded(): void
    {
        $path = '/it-bot-' . uniqid();

        try {
            $this->connector()->tracking->pageview(
                $this->websiteId,
                url: $path,
                hostname: $this->hostname,
                userAgent: 'curl/8.5.0',
            );
            $this->fail('Expected a BotFilteredException for a bot User-Agent.');
        } catch (BotFilteredException $e) {
            $this->assertSame(200, $e->getStatusCode());
        }

        $this->assertFalse(
            $this->waitForPath($path, 3.0),
            "A bot-filtered hit ({$path}) must never reach the stats.",
        );
    }

    public function testIdentifyIsAcceptedByTheServer(): void
    {
        $response = $this->connector()->tracking->identify(
            $this->websiteId,
            'it-user-' . uniqid(),
            data: ['plan' => 'pro'],
            userAgent: self::BROWSER_UA,
        );

        $this->assertSame(200, $response->status());
    }
}
