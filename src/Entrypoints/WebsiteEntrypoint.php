<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Entrypoints;

use AdrienGras\Umami\Entrypoints\Impl\AbstractEntrypoint;
use AdrienGras\Umami\Requests\Website\CreateWebsite;
use AdrienGras\Umami\Requests\Website\DeleteWebsite;
use AdrienGras\Umami\Requests\Website\GetWebsite;
use AdrienGras\Umami\Requests\Website\GetWebsiteDateRange;
use AdrienGras\Umami\Requests\Website\GetWebsiteValues;
use AdrienGras\Umami\Requests\Website\ListWebsites;
use AdrienGras\Umami\Requests\Website\ResetWebsite;
use AdrienGras\Umami\Requests\Website\TransferWebsite;
use AdrienGras\Umami\Requests\Website\UpdateWebsite;
use AdrienGras\Umami\Stats\Period;
use AdrienGras\Umami\Website\ReplayConfig;
use InvalidArgumentException;

/**
 * Website façade — `…/api/websites` CRUD and sub-routes.
 *
 * All calls require auth; the connector injects the Bearer obtained via
 * AuthEntrypoint::login(). Responses are returned as decoded arrays
 * (shapes documented in docs/API_UMAMI.md §4.2). Input guards live here.
 */
readonly class WebsiteEntrypoint extends AbstractEntrypoint
{
    /**
     * Paginated website list (`{data, count, page, pageSize}`).
     *
     * @return array<string, mixed>
     */
    public function list(
        ?int $page = null,
        ?int $pageSize = null,
        ?string $search = null,
        ?bool $includeTeams = null,
    ): array {
        $query = $this->compact([
            'page' => $page,
            'pageSize' => $pageSize,
            'search' => $search,
            'includeTeams' => $includeTeams,
        ]);

        return $this->asObject($this->api->send(new ListWebsites($query))->json());
    }

    /**
     * Single website by id.
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->asObject($this->api->send(new GetWebsite($this->websiteId($id)))->json());
    }

    /**
     * Create a website. `name` (≤100) and `domain` (≤500) are required.
     *
     * @return array<string, mixed>
     */
    public function create(
        string $name,
        string $domain,
        ?string $shareId = null,
        ?string $teamId = null,
        ?string $id = null,
    ): array {
        $name = $this->nonEmpty($name, 'name');
        $domain = $this->nonEmpty($domain, 'domain');

        if (mb_strlen($name) > 100) {
            throw new InvalidArgumentException('create() name must be at most 100 characters.');
        }

        if (mb_strlen($domain) > 500) {
            throw new InvalidArgumentException('create() domain must be at most 500 characters.');
        }

        $payload = $this->compact([
            'name' => $name,
            'domain' => $domain,
            'shareId' => $shareId,
            'teamId' => $teamId,
            'id' => $id,
        ]);

        return $this->asObject($this->api->send(new CreateWebsite($payload))->json());
    }

    /**
     * Update a website. All fields optional; only provided ones are sent.
     *
     * @return array<string, mixed>
     */
    public function update(
        string $id,
        ?string $name = null,
        ?string $domain = null,
        ?string $shareId = null,
        ?bool $replayEnabled = null,
        ?ReplayConfig $replayConfig = null,
    ): array {
        $payload = $this->compact([
            'name' => $name,
            'domain' => $domain,
            'shareId' => $shareId,
            'replayEnabled' => $replayEnabled,
            'replayConfig' => null === $replayConfig ? null : $replayConfig->toArray(),
        ]);

        return $this->asObject($this->api->send(new UpdateWebsite($this->websiteId($id), $payload))->json());
    }

    /**
     * Delete a website. Returns nothing.
     */
    public function delete(string $id): void
    {
        $this->api->send(new DeleteWebsite($this->websiteId($id)));
    }

    /**
     * Reset (wipe) a website's analytics data. Returns nothing.
     */
    public function reset(string $id): void
    {
        $this->api->send(new ResetWebsite($this->websiteId($id)));
    }

    /**
     * Transfer a website to a user OR a team — exactly one is required.
     *
     * @return array<string, mixed>
     */
    public function transfer(string $id, ?string $userId = null, ?string $teamId = null): array
    {
        $userId = null === $userId ? null : trim($userId);
        $teamId = null === $teamId ? null : trim($teamId);

        $hasUser = null !== $userId && '' !== $userId;
        $hasTeam = null !== $teamId && '' !== $teamId;

        if ($hasUser === $hasTeam) {
            throw new InvalidArgumentException('transfer() requires exactly one of userId or teamId.');
        }

        $payload = $hasUser ? ['userId' => $userId] : ['teamId' => $teamId];

        return $this->asObject($this->api->send(new TransferWebsite($this->websiteId($id), $payload))->json());
    }

    /**
     * Date span of the website's data (`{mindate, maxdate}`).
     *
     * @return array<string, mixed>
     */
    public function dateRange(string $id): array
    {
        return $this->asObject($this->api->send(new GetWebsiteDateRange($this->websiteId($id)))->json());
    }

    /**
     * Distinct values for a field (`type` ∈ EVENT_COLUMNS ∪ SESSION_COLUMNS),
     * over a period. Returns a list of `{value}` entries.
     *
     * @return list<array<string, mixed>>
     */
    public function values(string $id, string $type, Period $period, ?string $search = null): array
    {
        $type = $this->nonEmpty($type, 'type');

        $query = $this->compact(array_merge(
            $period->toQuery(),
            ['type' => $type, 'search' => $search],
        ));

        return $this->asList($this->api->send(new GetWebsiteValues($this->websiteId($id), $query))->json());
    }

    private function websiteId(string $id): string
    {
        return $this->nonEmpty($id, 'id');
    }

    private function nonEmpty(string $value, string $field): string
    {
        $value = trim($value);

        if ('' === $value) {
            throw new InvalidArgumentException(\sprintf('%s must not be empty.', $field));
        }

        return $value;
    }

    /**
     * Keep only non-null entries (optionals are never sent).
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function compact(array $values): array
    {
        return array_filter($values, static fn (mixed $value): bool => null !== $value);
    }
}
