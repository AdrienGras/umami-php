<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\User;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `DELETE /api/users/{id}` — delete a user, admin only (Bearer). */
class DeleteUser extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected readonly string $userId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/users/{$this->userId}";
    }
}
