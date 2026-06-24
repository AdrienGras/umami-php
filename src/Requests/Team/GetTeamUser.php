<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Team;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/teams/{id}/users/{userId}` — single team membership (Bearer). */
class GetTeamUser extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $teamId,
        protected readonly string $userId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/teams/{$this->teamId}/users/{$this->userId}";
    }
}
