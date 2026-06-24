<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Entrypoints;

use AdrienGras\Umami\Entrypoints\Impl\AbstractEntrypoint;
use AdrienGras\Umami\Enums\ReportType;
use AdrienGras\Umami\Requests\Report\CreateReport;
use AdrienGras\Umami\Requests\Report\DeleteReport;
use AdrienGras\Umami\Requests\Report\GenerateReport;
use AdrienGras\Umami\Requests\Report\GetReport;
use AdrienGras\Umami\Requests\Report\ListReports;
use AdrienGras\Umami\Requests\Report\UpdateReport;
use AdrienGras\Umami\Stats\Filters;
use stdClass;

/**
 * Report façade — `…/api/reports`.
 *
 * Two natures: a CRUD over saved reports, and nine ad-hoc generation endpoints
 * (`funnel`, `retention`, …). Generation keeps `parameters` as a free array —
 * the fine validation (steps, window, currency…) belongs to the live API
 * (golden rule 6), not the library. `Stats\Filters` and `Stats\Period` are
 * reused as-is for the report `filters` / dates. All calls require auth.
 */
readonly class ReportEntrypoint extends AbstractEntrypoint
{
    // ----- Saved reports (CRUD) -----

    /**
     * Paginated saved reports for a website (`{data, count, page, pageSize}`).
     * `websiteId` is required.
     *
     * @return array<string, mixed>
     */
    public function list(
        string $websiteId,
        ?ReportType $type = null,
        ?int $page = null,
        ?int $pageSize = null,
        ?string $search = null,
    ): array {
        $query = $this->compact([
            'websiteId' => $this->nonEmpty($websiteId, 'websiteId'),
            'type' => $type?->value,
            'page' => $page,
            'pageSize' => $pageSize,
            'search' => $search,
        ]);

        return $this->asObject($this->api->send(new ListReports($query))->json());
    }

    /**
     * Single saved report by id.
     *
     * @return array<string, mixed>
     */
    public function get(string $reportId): array
    {
        return $this->asObject($this->api->send(new GetReport($this->reportId($reportId)))->json());
    }

    /**
     * Create a saved report. `name` (≤200) and `parameters` are required;
     * `description` (≤500) optional.
     *
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    public function create(
        string $websiteId,
        ReportType $type,
        string $name,
        array $parameters,
        ?string $description = null,
    ): array {
        $payload = $this->compact([
            'websiteId' => $this->nonEmpty($websiteId, 'websiteId'),
            'type' => $type->value,
            'name' => $this->boundedString($name, 'name', 200),
            'parameters' => $parameters,
            'description' => null === $description ? null : $this->boundedString($description, 'description', 500),
        ]);

        return $this->asObject($this->api->send(new CreateReport($payload))->json());
    }

    /**
     * Update a saved report. Same body shape as {@see create()}.
     *
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    public function update(
        string $reportId,
        string $websiteId,
        ReportType $type,
        string $name,
        array $parameters,
        ?string $description = null,
    ): array {
        $reportId = $this->reportId($reportId);

        $payload = $this->compact([
            'websiteId' => $this->nonEmpty($websiteId, 'websiteId'),
            'type' => $type->value,
            'name' => $this->boundedString($name, 'name', 200),
            'parameters' => $parameters,
            'description' => null === $description ? null : $this->boundedString($description, 'description', 500),
        ]);

        return $this->asObject($this->api->send(new UpdateReport($reportId, $payload))->json());
    }

    /**
     * Delete a saved report. Returns nothing.
     */
    public function delete(string $reportId): void
    {
        $this->api->send(new DeleteReport($this->reportId($reportId)));
    }

    // ----- Generation (one method per report type) -----

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<array-key, mixed>
     */
    public function funnel(string $websiteId, array $parameters, ?Filters $filters = null): array
    {
        return $this->generate('funnel', $websiteId, $parameters, $filters);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<array-key, mixed>
     */
    public function retention(string $websiteId, array $parameters, ?Filters $filters = null): array
    {
        return $this->generate('retention', $websiteId, $parameters, $filters);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<array-key, mixed>
     */
    public function utm(string $websiteId, array $parameters, ?Filters $filters = null): array
    {
        return $this->generate('utm', $websiteId, $parameters, $filters);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<array-key, mixed>
     */
    public function goal(string $websiteId, array $parameters, ?Filters $filters = null): array
    {
        return $this->generate('goal', $websiteId, $parameters, $filters);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<array-key, mixed>
     */
    public function journey(string $websiteId, array $parameters, ?Filters $filters = null): array
    {
        return $this->generate('journey', $websiteId, $parameters, $filters);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<array-key, mixed>
     */
    public function revenue(string $websiteId, array $parameters, ?Filters $filters = null): array
    {
        return $this->generate('revenue', $websiteId, $parameters, $filters);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<array-key, mixed>
     */
    public function attribution(string $websiteId, array $parameters, ?Filters $filters = null): array
    {
        return $this->generate('attribution', $websiteId, $parameters, $filters);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<array-key, mixed>
     */
    public function performance(string $websiteId, array $parameters, ?Filters $filters = null): array
    {
        return $this->generate('performance', $websiteId, $parameters, $filters);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<array-key, mixed>
     */
    public function breakdown(string $websiteId, array $parameters, ?Filters $filters = null): array
    {
        return $this->generate('breakdown', $websiteId, $parameters, $filters);
    }

    /**
     * Run a generation endpoint. The response shape varies per type (object or
     * list) so it is returned as-is via {@see asArray()}.
     *
     * @param array<string, mixed> $parameters
     *
     * @return array<array-key, mixed>
     */
    private function generate(string $type, string $websiteId, array $parameters, ?Filters $filters): array
    {
        $filterParams = null === $filters ? [] : $filters->toQuery();

        $payload = [
            'websiteId' => $this->nonEmpty($websiteId, 'websiteId'),
            'type' => $type,
            'parameters' => $parameters,
            // The server validates `filters` with z.object — it is required and
            // must be an object, so an empty set is sent as `{}`, never `[]`.
            'filters' => [] === $filterParams ? new stdClass() : $filterParams,
        ];

        return $this->asArray($this->api->send(new GenerateReport($type, $payload))->json());
    }

    private function reportId(string $id): string
    {
        return $this->nonEmpty($id, 'reportId');
    }
}
