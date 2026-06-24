<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\User;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/admin/users` — paginated user list, admin only (Bearer). */
class ListUsers extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param array<string, mixed> $queryParams
     */
    public function __construct(
        protected readonly array $queryParams = [],
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/api/admin/users';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->queryParams;
    }
}
