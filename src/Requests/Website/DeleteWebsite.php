<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Website;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `DELETE /api/websites/{id}` — delete a website (Bearer). */
class DeleteWebsite extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected readonly string $websiteId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/websites/{$this->websiteId}";
    }
}
