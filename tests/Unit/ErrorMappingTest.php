<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit;

use AdrienGras\Umami\Exceptions\BotFilteredException;
use AdrienGras\Umami\Exceptions\UmamiApiException;
use AdrienGras\Umami\UmamiApi;
use PHPUnit\Framework\TestCase;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;

final class ErrorMappingTest extends TestCase
{
    private function connector(MockResponse $mock, ?string $apiToken = null): UmamiApi
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test', apiToken: $apiToken);
        $api->withMockClient(new MockClient([$mock]));

        return $api;
    }

    private function dummyRequest(): Request
    {
        return new class () extends Request {
            protected Method $method = Method::GET;

            public function resolveEndpoint(): string
            {
                return '/ping';
            }
        };
    }

    public function testBot200BeepBoopIsRequalifiedAsBotFilteredException(): void
    {
        $api = $this->connector(MockResponse::make(['beep' => 'boop'], 200));

        $this->expectException(BotFilteredException::class);

        $api->send($this->dummyRequest());
    }

    public function testHttpErrorThrowsTypedUmamiApiExceptionWithStatusAndErrorCode(): void
    {
        $api = $this->connector(MockResponse::make(
            ['error' => ['code' => 'bad-request', 'message' => 'Nope', 'status' => 400]],
            400,
        ));

        try {
            $api->send($this->dummyRequest());
            $this->fail('Expected a UmamiApiException to be thrown.');
        } catch (UmamiApiException $e) {
            $this->assertNotInstanceOf(BotFilteredException::class, $e);
            $this->assertSame(400, $e->getStatusCode());
            $this->assertSame('bad-request', $e->errorCode());
        }
    }
}
