<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Stats\Impl;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Base for `GET /api/websites/{id}/{segment}` stats requests. Dumb transport:
 * the entrypoint passes a ready-made query array. Bearer is injected by the
 * connector (these are not SkipsAuth).
 */
abstract class AbstractStatRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * Named $queryParams (not $query) — Saloon\Http\Request already declares a
     * non-readonly $query property; reusing the name is a fatal trait/property
     * collision (same family as the $body/$payload pitfall).
     *
     * @param array<string, mixed> $queryParams
     */
    public function __construct(
        protected readonly string $websiteId,
        protected readonly array $queryParams = [],
    ) {
    }

    abstract protected function segment(): string;

    public function resolveEndpoint(): string
    {
        return "/api/websites/{$this->websiteId}/{$this->segment()}";
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->queryParams;
    }
}
