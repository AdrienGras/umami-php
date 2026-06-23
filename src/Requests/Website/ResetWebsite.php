<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Website;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `POST /api/websites/{id}/reset` — wipe analytics data (Bearer). */
class ResetWebsite extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        protected readonly string $websiteId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/websites/{$this->websiteId}/reset";
    }
}
