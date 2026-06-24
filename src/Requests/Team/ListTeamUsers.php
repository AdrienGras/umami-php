<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Team;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/teams/{id}/users` — paginated team members (Bearer). */
class ListTeamUsers extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param array<string, mixed> $queryParams
     */
    public function __construct(
        protected readonly string $teamId,
        protected readonly array $queryParams = [],
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/teams/{$this->teamId}/users";
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->queryParams;
    }
}
