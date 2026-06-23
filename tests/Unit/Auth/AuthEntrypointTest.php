<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit\Auth;

use AdrienGras\Umami\Auth\LoginResult;
use AdrienGras\Umami\Contracts\SkipsAuth;
use AdrienGras\Umami\Entrypoints\AuthEntrypoint;
use AdrienGras\Umami\Exceptions\UmamiApiException;
use AdrienGras\Umami\Requests\Auth\Login;
use AdrienGras\Umami\UmamiApi;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

final class AuthEntrypointTest extends TestCase
{
    public function testLoginSendsCredentialsAndReturnsResult(): void
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test');
        $api->withMockClient(new MockClient([
            MockResponse::make(['token' => 'tok-1', 'user' => ['username' => 'admin', 'role' => 'admin']], 200),
        ]));

        $result = $api->auth->login('admin', 'umami');

        $this->assertInstanceOf(LoginResult::class, $result);
        $this->assertSame('tok-1', $result->token);
        $this->assertSame('admin', $result->user['username'] ?? null);
    }

    public function testLoginConfiguresConnectorTokenForSubsequentCalls(): void
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test');
        $api->withMockClient(new MockClient([
            MockResponse::make(['token' => 'tok-1', 'user' => []], 200),
            MockResponse::make(['ok' => true], 200),
        ]));

        $api->auth->login('admin', 'umami');

        $reporting = new class () extends \Saloon\Http\Request {
            protected \Saloon\Enums\Method $method = \Saloon\Enums\Method::GET;

            public function resolveEndpoint(): string
            {
                return '/api/websites';
            }
        };

        $headers = $api->send($reporting)->getPendingRequest()->headers()->all();

        $this->assertSame('Bearer tok-1', $headers['Authorization'] ?? null);
    }

    public function testLoginRequestIsPublicSkipsAuth(): void
    {
        $this->assertInstanceOf(SkipsAuth::class, new Login('admin', 'umami'));
    }

    public function testLoginRejectsEmptyUsername(): void
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test');

        $this->expectException(InvalidArgumentException::class);

        $api->auth->login('   ', 'umami');
    }

    public function testLoginRejectsEmptyPassword(): void
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test');

        $this->expectException(InvalidArgumentException::class);

        $api->auth->login('admin', '');
    }

    public function testLoginThrowsOnMissingTokenInResponse(): void
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test');
        $api->withMockClient(new MockClient([
            MockResponse::make(['user' => ['username' => 'admin']], 200),
        ]));

        $this->expectException(UmamiApiException::class);

        $api->auth->login('admin', 'umami');
    }

    public function testLogoutClearsConnectorToken(): void
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test', apiToken: 'tok-1');
        $api->withMockClient(new MockClient([
            MockResponse::make(['ok' => true], 200),
            MockResponse::make(['ok' => true], 200),
        ]));

        $api->auth->logout();

        $reporting = new class () extends \Saloon\Http\Request {
            protected \Saloon\Enums\Method $method = \Saloon\Enums\Method::GET;

            public function resolveEndpoint(): string
            {
                return '/api/websites';
            }
        };

        $headers = $api->send($reporting)->getPendingRequest()->headers()->all();

        $this->assertArrayNotHasKey('Authorization', $headers);
    }

    public function testVerifyReturnsUser(): void
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test', apiToken: 'tok-1');
        $api->withMockClient(new MockClient([
            MockResponse::make(['id' => 'u-1', 'username' => 'admin', 'isAdmin' => true], 200),
        ]));

        $user = $api->auth->verify();

        $this->assertSame('admin', $user['username'] ?? null);
    }

    public function testConnectorExposesAuthEntrypoint(): void
    {
        $api = new UmamiApi(baseUrl: 'http://umami.test');

        $this->assertInstanceOf(AuthEntrypoint::class, $api->auth);
    }
}
