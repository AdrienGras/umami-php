<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Team;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `DELETE /api/teams/{id}` — delete a team (Bearer). */
class DeleteTeam extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected readonly string $teamId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/teams/{$this->teamId}";
    }
}
