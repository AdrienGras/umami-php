<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Integration\Auth;

use AdrienGras\Umami\Exceptions\UmamiApiException;
use AdrienGras\Umami\Tests\Integration\IntegrationTestCase;

final class AuthIntegrationTest extends IntegrationTestCase
{
    public function testLoginReturnsTokenAndAdminUser(): void
    {
        $result = $this->connector()->auth->login($this->username(), $this->password());

        $this->assertNotSame('', $result->token);
        $this->assertSame($this->username(), $result->user['username'] ?? null);
        $this->assertTrue($result->user['isAdmin'] ?? false);
    }

    public function testLoginAuthenticatesSubsequentVerifyCall(): void
    {
        $api = $this->connector();
        $api->auth->login($this->username(), $this->password());

        $user = $api->auth->verify();

        $this->assertSame($this->username(), $user['username'] ?? null);
    }

    public function testLoginWithBadCredentialsThrows(): void
    {
        $this->expectException(UmamiApiException::class);

        $this->connector()->auth->login($this->username(), 'definitely-wrong-password');
    }
}
