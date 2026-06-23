<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Stats;

/**
 * Immutable date range for stats/reporting queries.
 *
 * Umami accepts two date contracts (withDateRange): epoch **milliseconds**
 * (`startAt`+`endAt`) OR ISO dates (`startDate`+`endDate`). Use the matching
 * named constructor; the value object guarantees a valid pair by construction.
 */
final readonly class Period
{
    private function __construct(
        public ?int $startAt = null,
        public ?int $endAt = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?string $timezone = null,
        public ?string $unit = null,
        public ?string $compare = null,
    ) {
    }

    /**
     * Range expressed in epoch **milliseconds**.
     */
    public static function between(
        int $startAt,
        int $endAt,
        ?string $timezone = null,
        ?string $unit = null,
        ?string $compare = null,
    ): self {
        return new self(
            startAt: $startAt,
            endAt: $endAt,
            timezone: $timezone,
            unit: $unit,
            compare: $compare,
        );
    }

    /**
     * Range expressed as dates (e.g. `2026-01-31` or any value Umami coerces).
     */
    public static function betweenDates(
        string $startDate,
        string $endDate,
        ?string $timezone = null,
        ?string $unit = null,
        ?string $compare = null,
    ): self {
        return new self(
            startDate: $startDate,
            endDate: $endDate,
            timezone: $timezone,
            unit: $unit,
            compare: $compare,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        $query = [
            'startAt' => $this->startAt,
            'endAt' => $this->endAt,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'timezone' => $this->timezone,
            'unit' => $this->unit,
            'compare' => $this->compare,
        ];

        return array_filter($query, static fn (mixed $value): bool => null !== $value);
    }
}
