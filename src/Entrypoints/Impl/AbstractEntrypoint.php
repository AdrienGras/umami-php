<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Entrypoints\Impl;

use AdrienGras\Umami\UmamiApi;

/**
 * Base class for every domain entrypoint.
 *
 * Holds the connector. Domain entrypoints (Tracking, Auth, Website, Stats, …)
 * extend this and turn clean arguments into Requests — this is where input
 * guards and transformations live (transport-only pattern, §2).
 */
abstract readonly class AbstractEntrypoint
{
    public function __construct(
        protected UmamiApi $api,
    ) {
    }
}
