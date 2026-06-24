<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Entrypoints;

use AdrienGras\Umami\Entrypoints\Impl\AbstractEntrypoint;
use AdrienGras\Umami\Enums\MetricType;
use AdrienGras\Umami\Requests\Stats\GetActiveVisitors;
use AdrienGras\Umami\Requests\Stats\GetEvents;
use AdrienGras\Umami\Requests\Stats\GetMetrics;
use AdrienGras\Umami\Requests\Stats\GetPageviews;
use AdrienGras\Umami\Requests\Stats\GetRealtime;
use AdrienGras\Umami\Requests\Stats\GetSessions;
use AdrienGras\Umami\Requests\Stats\GetStats;
use AdrienGras\Umami\Stats\Filters;
use AdrienGras\Umami\Stats\Period;
use InvalidArgumentException;

/**
 * Stats/reporting façade — `GET /api/websites/{id}/…`.
 *
 * All calls require auth; the connector injects the Bearer obtained via
 * AuthEntrypoint::login(). The {@see Period} value object enforces a valid date
 * pair (epoch ms or dates); {@see Filters} carries optional filters.
 *
 * Responses are returned as decoded arrays — shapes are documented in
 * docs/API_UMAMI.md §4.3.
 */
readonly class StatsEntrypoint extends AbstractEntrypoint
{
    /**
     * Website summary: pageviews, visitors, visits, bounces, totaltime (+comparison).
     *
     * @return array<string, mixed>
     */
    public function stats(string $websiteId, Period $period, ?Filters $filters = null): array
    {
        $request = new GetStats($this->website($websiteId), $this->query($period, $filters));

        return $this->asObject($this->api->send($request)->json());
    }

    /**
     * Breakdown by metric (`type` routes the dimension), as `[{x, y}, …]`.
     *
     * @return list<array<string, mixed>>
     */
    public function metrics(
        string $websiteId,
        MetricType $type,
        Period $period,
        ?Filters $filters = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $search = null,
    ): array {
        $query = $this->query($period, $filters, [
            'type' => $type->value,
            'limit' => $limit,
            'offset' => $offset,
            'search' => $search,
        ]);

        return $this->asList($this->api->send(new GetMetrics($this->website($websiteId), $query))->json());
    }

    /**
     * Pageviews/sessions time series: `{pageviews: [{x,y}], sessions: [{x,y}]}`.
     *
     * @return array<string, mixed>
     */
    public function pageviews(string $websiteId, Period $period, ?Filters $filters = null): array
    {
        $request = new GetPageviews($this->website($websiteId), $this->query($period, $filters));

        return $this->asObject($this->api->send($request)->json());
    }

    /**
     * Paginated events list (`{data, count, page, pageSize}`).
     *
     * @return array<string, mixed>
     */
    public function events(
        string $websiteId,
        Period $period,
        ?Filters $filters = null,
        ?int $page = null,
        ?int $pageSize = null,
        ?string $search = null,
    ): array {
        $query = $this->query($period, $filters, [
            'page' => $page,
            'pageSize' => $pageSize,
            'search' => $search,
        ]);

        return $this->asObject($this->api->send(new GetEvents($this->website($websiteId), $query))->json());
    }

    /**
     * Paginated sessions list (`{data, count, page, pageSize}`).
     *
     * @return array<string, mixed>
     */
    public function sessions(
        string $websiteId,
        Period $period,
        ?Filters $filters = null,
        ?int $page = null,
        ?int $pageSize = null,
        ?string $search = null,
    ): array {
        $query = $this->query($period, $filters, [
            'page' => $page,
            'pageSize' => $pageSize,
            'search' => $search,
        ]);

        return $this->asObject($this->api->send(new GetSessions($this->website($websiteId), $query))->json());
    }

    /**
     * Currently active visitors (last 30 min window).
     *
     * @return list<array<string, mixed>>
     */
    public function active(string $websiteId): array
    {
        return $this->asList($this->api->send(new GetActiveVisitors($this->website($websiteId)))->json());
    }

    /**
     * Realtime activity (served by the dedicated `/api/realtime/{id}` route).
     * Returns `{countries, urls, referrers, events, series, totals, timestamp}`.
     *
     * @return array<string, mixed>
     */
    public function realtime(string $websiteId): array
    {
        return $this->asObject($this->api->send(new GetRealtime($this->website($websiteId)))->json());
    }

    private function website(string $websiteId): string
    {
        $websiteId = trim($websiteId);

        if ('' === $websiteId) {
            throw new InvalidArgumentException('websiteId must not be empty.');
        }

        return $websiteId;
    }

    /**
     * Merge the date range, optional filters and extra (non-null) params.
     *
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function query(Period $period, ?Filters $filters, array $extra = []): array
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
}
