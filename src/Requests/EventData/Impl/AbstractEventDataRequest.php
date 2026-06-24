<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\EventData\Impl;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Base for the `…/event-data[/segment]` GET endpoints. Subclasses only differ
 * by their path suffix ({@see path()}).
 */
abstract class AbstractEventDataRequest extends Request
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
        return "/api/websites/{$this->websiteId}/event-data" . $this->path();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->queryParams;
    }

    /** Path suffix appended after `…/event-data` (empty for the list route). */
    protected function path(): string
    {
        return '';
    }
}
