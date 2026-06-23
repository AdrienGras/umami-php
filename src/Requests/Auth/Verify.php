<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Auth;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * `POST /api/auth/verify` — Bearer required. Returns the current user.
 */
class Verify extends Request
{
    protected Method $method = Method::POST;

    public function resolveEndpoint(): string
    {
        return '/api/auth/verify';
    }
}
