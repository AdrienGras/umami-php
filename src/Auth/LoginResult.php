<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Auth;

/**
 * Outcome of a successful login: the reporting Bearer token and the raw user
 * object returned by `POST /api/auth/login`.
 *
 * The connector is already configured with {@see $token} after login(); keep
 * this object to persist the token across your own HTTP requests.
 */
final readonly class LoginResult
{
    /**
     * @param array<string, mixed> $user
     */
    public function __construct(
        public string $token,
        public array $user,
    ) {
    }
}
