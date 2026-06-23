<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit\Tracking;

use AdrienGras\Umami\Contracts\SkipsAuth;
use AdrienGras\Umami\Entrypoints\TrackingEntrypoint;
use AdrienGras\Umami\Requests\Tracking\SendBatch;
use AdrienGras\Umami\Requests\Tracking\SendHit;
use AdrienGras\Umami\Tracking\Payload;
use AdrienGras\Umami\UmamiApi;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;

final class TrackingEntrypointTest extends TestCase
{
    /**
     * Run an entrypoint call against a mocked connector and return the JSON
     * body actually built for the outgoing request.
     *
     * @param callable(TrackingEntrypoint): Response $call
     *
     * @return array<array-key, mixed>
     */
    private function sentBody(callable $call): array
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test');
        $api->withMockClient(new MockClient([MockResponse::make(['cache' => 'x'], 200)]));

        $tracking = new TrackingEntrypoint($api);
        $response = $call($tracking);

        $repository = $response->getPendingRequest()->body();
        $body = null === $repository ? [] : $repository->all();

        return is_array($body) ? $body : [];
    }

    public function testPageviewBuildsEventHitBody(): void
    {
        $body = $this->sentBody(fn (TrackingEntrypoint $t) => $t->pageview(
            'w-1',
            url: '/home',
            title: 'Home',
            referrer: '/',
        ));

        $this->assertEquals([
            'type' => 'event',
            'payload' => [
                'website' => 'w-1',
                'url' => '/home',
                'title' => 'Home',
                'referrer' => '/',
            ],
        ], $body);
    }

    public function testEventBuildsEventHitWithNameAndData(): void
    {
        $body = $this->sentBody(fn (TrackingEntrypoint $t) => $t->event(
            'w-1',
            'signup',
            data: ['plan' => 'pro'],
        ));

        $this->assertEquals([
            'type' => 'event',
            'payload' => [
                'website' => 'w-1',
                'name' => 'signup',
                'data' => ['plan' => 'pro'],
            ],
        ], $body);
    }

    public function testIdentifyBuildsIdentifyHitWithDistinctId(): void
    {
        $body = $this->sentBody(fn (TrackingEntrypoint $t) => $t->identify(
            'w-1',
            'user-42',
            data: ['email' => 'x@y.z'],
        ));

        $this->assertEquals([
            'type' => 'identify',
            'payload' => [
                'website' => 'w-1',
                'data' => ['email' => 'x@y.z'],
                'id' => 'user-42',
            ],
        ], $body);
    }

    public function testBatchBuildsArrayOfHits(): void
    {
        $body = $this->sentBody(fn (TrackingEntrypoint $t) => $t->batch([
            new Payload(website: 'w-1', url: '/a'),
            new Payload(website: 'w-1', url: '/b'),
        ]));

        $this->assertEquals([
            ['type' => 'event', 'payload' => ['website' => 'w-1', 'url' => '/a']],
            ['type' => 'event', 'payload' => ['website' => 'w-1', 'url' => '/b']],
        ], $body);
    }

    public function testSendRejectsPayloadWithNoTarget(): void
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test');
        $tracking = new TrackingEntrypoint($api);

        $this->expectException(InvalidArgumentException::class);

        $tracking->send(new Payload(url: '/no-target'));
    }

    public function testSendRejectsPayloadWithTwoTargets(): void
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test');
        $tracking = new TrackingEntrypoint($api);

        $this->expectException(InvalidArgumentException::class);

        $tracking->send(new Payload(website: 'w-1', pixel: 'p-1'));
    }

    public function testEventRejectsEmptyName(): void
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test');
        $tracking = new TrackingEntrypoint($api);

        $this->expectException(InvalidArgumentException::class);

        $tracking->event('w-1', '   ');
    }

    public function testTrackingRequestsAreSkipsAuth(): void
    {
        $this->assertInstanceOf(SkipsAuth::class, new SendHit('event', ['website' => 'w-1']));
        $this->assertInstanceOf(SkipsAuth::class, new SendBatch([]));
    }
}
