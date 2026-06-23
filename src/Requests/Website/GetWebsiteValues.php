<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Website;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/websites/{id}/values` — distinct values for a field (Bearer). */
class GetWebsiteValues extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param array<string, mixed> $queryParams
     */
    public function __construct(
        protected readonly string $websiteId,
        protected readonly array $queryParams = [],
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/websites/{$this->websiteId}/values";
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->queryParams;
    }
}
