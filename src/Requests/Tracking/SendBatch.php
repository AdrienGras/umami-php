<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Tracking;

use AdrienGras\Umami\Contracts\SkipsAuth;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * `POST /api/batch` — an array of `{type, payload}` hits. Public endpoint
 * (skipAuth), so it carries no Bearer ({@see SkipsAuth}). The hits are already
 * prepared by the entrypoint; the JSON body is an array at the root.
 */
class SendBatch extends Request implements HasBody, SkipsAuth
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param array<int, array{type: string, payload: array<string, mixed>}> $hits
     */
    public function __construct(
        private readonly array $hits,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/api/batch';
    }

    /**
     * @return array<int, array{type: string, payload: array<string, mixed>}>
     */
    protected function defaultBody(): array
    {
        return $this->hits;
    }
}
