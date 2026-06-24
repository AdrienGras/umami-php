<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\EventData;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** `GET /api/websites/{id}/event-data/{eventId}` — one event's data (Bearer). */
class GetEventDataDetail extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $websiteId,
        protected readonly string $eventId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return "/api/websites/{$this->websiteId}/event-data/{$this->eventId}";
    }
}
