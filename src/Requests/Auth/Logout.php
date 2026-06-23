<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Auth;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * `POST /api/auth/logout` — Bearer required. Note: server-side this is a no-op
 * unless Redis is enabled (the JWT is stateless), so the lib also forgets the
 * token client-side.
 */
class Logout extends Request
{
    protected Method $method = Method::POST;

    public function resolveEndpoint(): string
    {
        return '/api/auth/logout';
    }
}
