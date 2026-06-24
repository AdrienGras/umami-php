<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Report;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * `POST /api/reports/{type}` — run an ad-hoc report of a given type (Bearer).
 *
 * One request for the nine generation endpoints; the type is the path segment.
 */
class GenerateReport extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $reportType,
        private readonly array $payload,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/reports/{$this->reportType}";
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->payload;
    }
}
