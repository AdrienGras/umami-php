<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Report;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/reports` — paginated saved reports for a website (Bearer). */
class ListReports extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param array<string, mixed> $queryParams
     */
    public function __construct(
        protected readonly array $queryParams = [],
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/api/reports';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->queryParams;
    }
}
