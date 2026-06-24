<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Report;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/** `POST /api/reports/{id}` — update a saved report (Bearer). */
class UpdateReport extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $reportId,
        private readonly array $payload,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/reports/{$this->reportId}";
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->payload;
    }
}
