<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Team;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/** `POST /api/teams/{id}` — update a team (Bearer). */
class UpdateTeam extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $teamId,
        private readonly array $payload,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/teams/{$this->teamId}";
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->payload;
    }
}
