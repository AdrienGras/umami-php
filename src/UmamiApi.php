<?php

declare(strict_types=1);

namespace AdrienGras\Umami;

use AdrienGras\Umami\Contracts\SkipsAuth;
use AdrienGras\Umami\Entrypoints\AuthEntrypoint;
use AdrienGras\Umami\Entrypoints\StatsEntrypoint;
use AdrienGras\Umami\Entrypoints\TrackingEntrypoint;
use AdrienGras\Umami\Entrypoints\UserEntrypoint;
use AdrienGras\Umami\Entrypoints\WebsiteEntrypoint;
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

    /** Auth façade: login/logout/verify. */
    public readonly AuthEntrypoint $auth;

    /** Stats/reporting façade: stats/metrics/pageviews/events/sessions/active. */
    public readonly StatsEntrypoint $stats;

    /** Website façade: CRUD + reset/transfer/daterange/values. */
    public readonly WebsiteEntrypoint $websites;

    /** User façade: CRUD + admin listing + teams/websites sub-routes. */
    public readonly UserEntrypoint $users;

    /**
     * Current reporting Bearer token. Seeded from $apiToken, then updated by
     * {@see withToken()} (e.g. after AuthEntrypoint::login()).
     */
    private ?string $bearerToken;

    public function __construct(
        public readonly string $baseUrl,
        public readonly ?string $apiToken = null,
        public readonly bool $useDebug = false,
    ) {
        $this->bearerToken = $apiToken;
        $this->tracking = new TrackingEntrypoint($this);
        $this->auth = new AuthEntrypoint($this);
        $this->stats = new StatsEntrypoint($this);
        $this->websites = new WebsiteEntrypoint($this);
        $this->users = new UserEntrypoint($this);

        if ($this->useDebug) {
            $this->debug();
        }

        // Inject the current reporting Bearer on every request except tracking
        // ones (SkipsAuth) — Umami's tracking endpoints are skipAuth server-side.
        $this->middleware()->onRequest(function (PendingRequest $pendingRequest): void {
            if (null === $this->bearerToken) {
                return;
            }

            if ($pendingRequest->getRequest() instanceof SkipsAuth) {
                return;
            }

            $pendingRequest->headers()->add('Authorization', 'Bearer ' . $this->bearerToken);
        });
    }

    /**
     * Set (or clear, with null) the reporting Bearer token used for subsequent
     * non-tracking requests. Returns $this for chaining.
     */
    public function withToken(?string $token): static
    {
        $this->bearerToken = $token;

        return $this;
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
