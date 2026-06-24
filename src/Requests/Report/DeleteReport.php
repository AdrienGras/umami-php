<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Report;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `DELETE /api/reports/{id}` — delete a saved report (Bearer). */
class DeleteReport extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected readonly string $reportId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/reports/{$this->reportId}";
    }
}
