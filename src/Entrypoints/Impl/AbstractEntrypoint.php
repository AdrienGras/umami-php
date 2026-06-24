<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Entrypoints\Impl;

use AdrienGras\Umami\UmamiApi;
use InvalidArgumentException;

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

    /**
     * Return the decoded JSON as-is when it is already an array (list OR
     * object), else an empty array. Unlike {@see asObject()}/{@see asList()},
     * this preserves the native shape — useful where the same family of
     * endpoints answers with either form (e.g. report generation: `funnel`
     * returns a list, `utm` returns an object).
     *
     * @return array<array-key, mixed>
     */
    protected function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * Keep only non-null entries — optional fields are never sent.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    protected function compact(array $values): array
    {
        return array_filter($values, static fn (mixed $value): bool => null !== $value);
    }

    /**
     * Trim a value and reject it when empty.
     *
     * @throws InvalidArgumentException when the trimmed value is empty
     */
    protected function nonEmpty(string $value, string $field): string
    {
        $value = trim($value);

        if ('' === $value) {
            throw new InvalidArgumentException(\sprintf('%s must not be empty.', $field));
        }

        return $value;
    }

    /**
     * Trim a value, reject it when empty, then enforce a maximum length.
     *
     * @throws InvalidArgumentException when empty or longer than $maxLength
     */
    protected function boundedString(string $value, string $field, int $maxLength): string
    {
        $value = $this->nonEmpty($value, $field);

        if (mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException(\sprintf('%s must be at most %d characters.', $field, $maxLength));
        }

        return $value;
    }
}
