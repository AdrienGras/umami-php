<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\User;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/users/{id}/websites` — paginated websites of a user (Bearer). */
class GetUserWebsites extends Request
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
        return "/api/users/{$this->userId}/websites";
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->queryParams;
    }
}
