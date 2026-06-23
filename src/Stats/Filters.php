<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Stats;

/**
 * Immutable set of optional stats filters (filterParams, schema.ts).
 *
 * Every field is optional; {@see toQuery()} drops nulls. `segment`/`cohort` are
 * UUIDs, `eventType` an EVENT_TYPE int, `match` one of `all`/`any`.
 */
final readonly class Filters
{
    public function __construct(
        public ?string $path = null,
        public ?string $referrer = null,
        public ?string $title = null,
        public ?string $query = null,
        public ?string $os = null,
        public ?string $browser = null,
        public ?string $device = null,
        public ?string $country = null,
        public ?string $region = null,
        public ?string $city = null,
        public ?string $tag = null,
        public ?string $hostname = null,
        public ?string $distinctId = null,
        public ?string $language = null,
        public ?string $event = null,
        public ?string $utmSource = null,
        public ?string $utmMedium = null,
        public ?string $utmCampaign = null,
        public ?string $utmContent = null,
        public ?string $utmTerm = null,
        public ?string $segment = null,
        public ?string $cohort = null,
        public ?int $eventType = null,
        public ?string $excludeBounce = null,
        public ?string $match = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        $query = [
            'path' => $this->path,
            'referrer' => $this->referrer,
            'title' => $this->title,
            'query' => $this->query,
            'os' => $this->os,
            'browser' => $this->browser,
            'device' => $this->device,
            'country' => $this->country,
            'region' => $this->region,
            'city' => $this->city,
            'tag' => $this->tag,
            'hostname' => $this->hostname,
            'distinctId' => $this->distinctId,
            'language' => $this->language,
            'event' => $this->event,
            'utmSource' => $this->utmSource,
            'utmMedium' => $this->utmMedium,
            'utmCampaign' => $this->utmCampaign,
            'utmContent' => $this->utmContent,
            'utmTerm' => $this->utmTerm,
            'segment' => $this->segment,
            'cohort' => $this->cohort,
            'eventType' => $this->eventType,
            'excludeBounce' => $this->excludeBounce,
            'match' => $this->match,
        ];

        return array_filter($query, static fn (mixed $value): bool => null !== $value);
    }
}
