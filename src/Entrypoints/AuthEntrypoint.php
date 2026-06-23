<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Entrypoints;

use AdrienGras\Umami\Auth\LoginResult;
use AdrienGras\Umami\Entrypoints\Impl\AbstractEntrypoint;
use AdrienGras\Umami\Exceptions\UmamiApiException;
use AdrienGras\Umami\Requests\Auth\Login;
use AdrienGras\Umami\Requests\Auth\Logout;
use AdrienGras\Umami\Requests\Auth\Verify;
use InvalidArgumentException;

/**
 * Authentication façade — `POST /api/auth/{login,logout,verify}`.
 *
 * {@see login()} authenticates and configures the connector with the returned
 * Bearer token, so subsequent reporting calls are authenticated automatically.
 */
readonly class AuthEntrypoint extends AbstractEntrypoint
{
    /**
     * Authenticate and store the Bearer token on the connector.
     *
     * @throws InvalidArgumentException on empty credentials
     * @throws UmamiApiException        on bad credentials (401) or missing token
     */
    public function login(string $username, string $password): LoginResult
    {
        $username = trim($username);

        if ('' === $username) {
            throw new InvalidArgumentException('login() username must not be empty.');
        }

        if ('' === $password) {
            throw new InvalidArgumentException('login() password must not be empty.');
        }

        $response = $this->api->send(new Login($username, $password));

        $token = $response->json('token');

        if (!is_string($token) || '' === $token) {
            throw new UmamiApiException($response, 'Login succeeded but no token was returned.');
        }

        $this->api->withToken($token);

        return new LoginResult($token, $this->asObject($response->json('user')));
    }

    /**
     * Log out and forget the token client-side.
     *
     * Server-side this is a no-op without Redis (stateless JWT), so the token
     * may remain technically valid; the lib stops sending it regardless.
     */
    public function logout(): void
    {
        $this->api->send(new Logout());
        $this->api->withToken(null);
    }

    /**
     * Verify the current token and return the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function verify(): array
    {
        return $this->asObject($this->api->send(new Verify())->json());
    }

    /**
     * Normalise a decoded JSON value into a string-keyed array (or empty).
     *
     * @return array<string, mixed>
     */
    private function asObject(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $object = [];

        foreach ($value as $key => $item) {
            $object[(string) $key] = $item;
        }

        return $object;
    }
}
