<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Tracking;

use AdrienGras\Umami\Contracts\SkipsAuth;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * `POST /api/send` — a single tracking hit. Public endpoint (skipAuth), so it
 * carries no Bearer ({@see SkipsAuth}). The payload is already prepared by the
 * entrypoint; this request stays dumb.
 */
class SendHit extends Request implements HasBody, SkipsAuth
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $type,
        private readonly array $payload,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/api/send';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'type' => $this->type,
            'payload' => $this->payload,
        ];
    }
}
