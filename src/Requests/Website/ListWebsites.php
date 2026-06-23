<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Website;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/websites` — paginated website list (Bearer). */
class ListWebsites extends Request
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
        return '/api/websites';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->queryParams;
    }
}
