<?php

declare(strict_types=1);

namespace AdrienGras\Umami;

use AdrienGras\Umami\Contracts\SkipsAuth;
use AdrienGras\Umami\Entrypoints\TrackingEntrypoint;
use AdrienGras\Umami\Responses\UmamiApiResponse;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

/**
 * Umami API connector (transport layer).
 *
 * Instantiate explicitly with resolved values — this package is framework-free,
 * so there is no DI binding and no silent default base URL.
 */
class UmamiApi extends Connector
{
    use AlwaysThrowOnErrors;
    use AcceptsJson;

    /** @var class-string<\Saloon\Http\Response>|null */
    protected ?string $response = UmamiApiResponse::class;

    /** Tracking façade: `/api/send`, `/api/batch`. */
    public readonly TrackingEntrypoint $tracking;

    public function __construct(
        public readonly string $baseUrl,
        public readonly ?string $apiToken = null,
        public readonly bool $useDebug = false,
    ) {
        $this->tracking = new TrackingEntrypoint($this);

        if ($this->useDebug) {
            $this->debug();
        }

        // Inject the reporting Bearer on every request except tracking ones
        // (SkipsAuth) — Umami's tracking endpoints are skipAuth server-side.
        if (null !== $this->apiToken) {
            $this->middleware()->onRequest(function (PendingRequest $pendingRequest): void {
                if ($pendingRequest->getRequest() instanceof SkipsAuth) {
                    return;
                }

                $pendingRequest->headers()->add('Authorization', 'Bearer ' . $this->apiToken);
            });
        }
    }

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /** @return array<string, string> */
    public function defaultHeaders(): array
    {
        return ['User-Agent' => 'umami-php/1.0 (+https://github.com/AdrienGras/umami-php)'];
    }

    /** @return array<string, mixed> */
    public function defaultConfig(): array
    {
        return ['timeout' => 30];
    }
}
