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

    /**
     * Normalise a decoded JSON value into a string-keyed array (or empty).
     *
     * @return array<string, mixed>
     */
    protected function asObject(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $object = [];

        foreach ($value as $key => $item) {
            $object[(string) $key] = $item;
        }

        return $object;
    }

    /**
     * Normalise a decoded JSON array into a list of string-keyed arrays.
     *
     * @return list<array<string, mixed>>
     */
    protected function asList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $list = [];

        foreach ($value as $item) {
            $list[] = $this->asObject($item);
        }

        return $list;
    }
}
