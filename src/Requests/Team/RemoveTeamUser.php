<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Team;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `DELETE /api/teams/{id}/users/{userId}` — remove a member from a team (Bearer). */
class RemoveTeamUser extends Request
{
    protected Method $method = Method::DELETE;

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
