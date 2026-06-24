<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Team;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/teams/{id}` — single team with its members (Bearer). */
class GetTeam extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $teamId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/teams/{$this->teamId}";
    }
}
