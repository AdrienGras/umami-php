<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Entrypoints;

use AdrienGras\Umami\Entrypoints\Impl\AbstractEntrypoint;
use AdrienGras\Umami\Requests\EventData\GetEventDataDetail;
use AdrienGras\Umami\Requests\EventData\GetEventDataEvents;
use AdrienGras\Umami\Requests\EventData\GetEventDataFields;
use AdrienGras\Umami\Requests\EventData\GetEventDataProperties;
use AdrienGras\Umami\Requests\EventData\GetEventDataStats;
use AdrienGras\Umami\Requests\EventData\GetEventDataValues;
use AdrienGras\Umami\Requests\EventData\ListEventData;
use AdrienGras\Umami\Stats\Filters;
use AdrienGras\Umami\Stats\Period;

/**
 * Event-data façade — `…/api/websites/{id}/event-data*`.
 *
 * Explores custom-event properties. All endpoints need a `startAt`/`endAt`
 * window in epoch milliseconds, so pass a {@see Period} built with
 * {@see Period::between()}. Reuses {@see Filters}. All calls require auth.
 */
readonly class EventDataEntrypoint extends AbstractEntrypoint
{
    /**
     * Paginated event-data records (`{data, count, page, pageSize}`); each row
     * carries its `eventProperties`.
     *
     * @return array<string, mixed>
     */
    public function list(
        string $websiteId,
        Period $period,
        ?Filters $filters = null,
        ?int $page = null,
        ?int $pageSize = null,
    ): array {
        $query = $this->eventQuery($period, $filters, ['page' => $page, 'pageSize' => $pageSize]);

        return $this->asObject($this->api->send(new ListEventData($this->website($websiteId), $query))->json());
    }

    /**
     * A single event's data by event id.
     *
     * @return array<string, mixed>
     */
    public function get(string $websiteId, string $eventId): array
    {
        return $this->asObject(
            $this->api->send(new GetEventDataDetail($this->website($websiteId), $this->nonEmpty($eventId, 'eventId')))->json(),
        );
    }

    /**
     * Property breakdown for one event. `event` is required — the live endpoint
     * answers `500` when it is omitted (stricter than the source schema).
     *
     * @return list<array<string, mixed>>
     */
    public function events(string $websiteId, Period $period, string $event, ?Filters $filters = null): array
    {
        $query = $this->eventQuery($period, $filters, ['event' => $this->nonEmpty($event, 'event')]);

        return $this->asList($this->api->send(new GetEventDataEvents($this->website($websiteId), $query))->json());
    }

    /**
     * Available event fields over the window.
     *
     * @return list<array<string, mixed>>
     */
    public function fields(string $websiteId, Period $period, ?Filters $filters = null): array
    {
        $query = $this->eventQuery($period, $filters);

        return $this->asList($this->api->send(new GetEventDataFields($this->website($websiteId), $query))->json());
    }

    /**
     * Event property names over the window.
     *
     * @return list<array<string, mixed>>
     */
    public function properties(string $websiteId, Period $period, ?Filters $filters = null): array
    {
        $query = $this->eventQuery($period, $filters);

        return $this->asList($this->api->send(new GetEventDataProperties($this->website($websiteId), $query))->json());
    }

    /**
     * Distinct values of `propertyName` for `event` (both required).
     *
     * @return list<array<string, mixed>>
     */
    public function values(
        string $websiteId,
        Period $period,
        string $event,
        string $propertyName,
        ?Filters $filters = null,
    ): array {
        $query = $this->eventQuery($period, $filters, [
            'event' => $this->nonEmpty($event, 'event'),
            'propertyName' => $this->nonEmpty($propertyName, 'propertyName'),
        ]);

        return $this->asList($this->api->send(new GetEventDataValues($this->website($websiteId), $query))->json());
    }

    /**
     * Aggregate event-data counts (`{events, properties, records}`).
     *
     * @return array<string, mixed>
     */
    public function stats(string $websiteId, Period $period, ?Filters $filters = null): array
    {
        $query = $this->eventQuery($period, $filters);

        return $this->asObject($this->api->send(new GetEventDataStats($this->website($websiteId), $query))->json());
    }

    /**
     * Merge the (epoch-ms) date range, optional filters and extra non-null params.
     *
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function eventQuery(Period $period, ?Filters $filters, array $extra = []): array
    {
        $query = array_merge(
            $period->toQuery(),
            null === $filters ? [] : $filters->toQuery(),
        );

        foreach ($extra as $key => $value) {
            if (null !== $value) {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    private function website(string $websiteId): string
    {
        return $this->nonEmpty($websiteId, 'websiteId');
    }
}
