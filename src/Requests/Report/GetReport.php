<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Report;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/reports/{id}` — single saved report (Bearer). */
class GetReport extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $reportId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/reports/{$this->reportId}";
    }
}
