<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit;

use AdrienGras\Umami\Contracts\SkipsAuth;
use AdrienGras\Umami\UmamiApi;
use PHPUnit\Framework\TestCase;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;

final class AuthRegimeTest extends TestCase
{
    /** @return array<string, mixed> */
    private function send(Request $request, ?string $apiToken): array
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test', apiToken: $apiToken);
        $api->withMockClient(new MockClient([MockResponse::make(['ok' => true], 200)]));

        $response = $api->send($request);

        return $response->getPendingRequest()->headers()->all();
    }

    private function reportingRequest(): Request
    {
        return new class () extends Request {
            protected Method $method = Method::GET;

            public function resolveEndpoint(): string
            {
                return '/api/websites';
            }
        };
    }

    private function trackingRequest(): Request
    {
        return new class () extends Request implements SkipsAuth {
            protected Method $method = Method::POST;

            public function resolveEndpoint(): string
            {
                return '/api/send';
            }
        };
    }

    public function testReportingRequestCarriesBearerToken(): void
    {
        $headers = $this->send($this->reportingRequest(), 'secret-token');

        $this->assertSame('Bearer secret-token', $headers['Authorization'] ?? null);
    }

    public function testTrackingRequestIsExcludedFromBearer(): void
    {
        $headers = $this->send($this->trackingRequest(), 'secret-token');

        $this->assertArrayNotHasKey('Authorization', $headers);
    }

    public function testNoTokenMeansNoAuthorizationHeader(): void
    {
        $headers = $this->send($this->reportingRequest(), null);

        $this->assertArrayNotHasKey('Authorization', $headers);
    }

    public function testWithTokenInjectsBearerAfterConstruction(): void
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test');
        $api->withMockClient(new MockClient([MockResponse::make(['ok' => true], 200)]));
        $api->withToken('late-token');

        $headers = $api->send($this->reportingRequest())->getPendingRequest()->headers()->all();

        $this->assertSame('Bearer late-token', $headers['Authorization'] ?? null);
    }

    public function testWithTokenNullClearsBearer(): void
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test', apiToken: 'initial');
        $api->withMockClient(new MockClient([MockResponse::make(['ok' => true], 200)]));
        $api->withToken(null);

        $headers = $api->send($this->reportingRequest())->getPendingRequest()->headers()->all();

        $this->assertArrayNotHasKey('Authorization', $headers);
    }
}
