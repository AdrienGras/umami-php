<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Team;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/admin/teams` — paginated list of all teams, admin only (Bearer). */
class ListAllTeams extends Request
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
        return '/api/admin/teams';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->queryParams;
    }
}
