<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Website;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/websites/{id}/daterange` — data date span (Bearer). */
class GetWebsiteDateRange extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $websiteId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/websites/{$this->websiteId}/daterange";
    }
}
