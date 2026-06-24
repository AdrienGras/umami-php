<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\User;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/users/{id}` — single user (Bearer). */
class GetUser extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $userId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/users/{$this->userId}";
    }
}
