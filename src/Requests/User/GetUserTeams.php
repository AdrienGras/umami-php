<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\User;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/users/{id}/teams` — paginated teams of a user (Bearer). */
class GetUserTeams extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param array<string, mixed> $queryParams
     */
    public function __construct(
        protected readonly string $userId,
        protected readonly array $queryParams = [],
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/users/{$this->userId}/teams";
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->queryParams;
    }
}
