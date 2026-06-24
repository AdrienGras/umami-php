<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Stats;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/realtime/{id}` — realtime activity window (Bearer). */
class GetRealtime extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $websiteId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/realtime/{$this->websiteId}";
    }
}
